<?php

declare(strict_types=1);

namespace Modules\Licensing\Patching\Core;

use Closure;
use PDO;
use ZipArchive;

/**
 * هسته‌ی نصب پچ (بدون وابستگی به Laravel):
 *
 *  preflight → دانلود(resume) → sha256 → امضای Ed25519 → اعتبارسنجی manifest/zip
 *  → staging → بکاپ (فایل‌ها + دیتابیس) → اعمال فایل‌ها → php -l
 *  → SQL در یک تراکنش (+ledger، foreign_key_check، مقایسه‌ی تعداد ردیف‌ها)
 *  → healthcheck → پایان
 *
 * هر خطا پس از شروع تغییرات = بازگردانی فایل‌ها (و در صورت commit شدن SQL، دیتابیس) و پرتاب PatchFailure.
 *
 * قرارداد بسته‌ی zip:  manifest.json | files/<مسیر نسبی> | sql/<اسکریپت>
 */
final class Installer
{
    public function __construct(
        private readonly PatchEnv $env,
        private readonly Downloader $downloader = new Downloader(),
    ) {}

    // ───────────────────────── preflight ─────────────────────────

    /**
     * @param array<string,mixed> $offer
     * @param list<string>        $appliedCodes
     */
    public function preflight(array $offer, string $currentVersion, array $appliedCodes): void
    {
        if (in_array($offer['patch_code'], $appliedCodes, true)) {
            throw new PatchFailure('ALREADY_APPLIED', 'این پچ قبلاً اعمال شده است.');
        }

        $cur = Util::semCode($currentVersion);
        if ($cur < Util::semCode((string) $offer['from_min']) || $cur > Util::semCode((string) $offer['from_max'])) {
            throw new PatchFailure('VERSION_OUT_OF_RANGE', "نسخه‌ی فعلی {$currentVersion} خارج از بازه‌ی {$offer['from_min']} تا {$offer['from_max']} است.");
        }
        if (Util::semCode((string) $offer['to_version']) <= $cur) {
            throw new PatchFailure('NOT_NEWER', 'نسخه‌ی مقصد جدیدتر از نسخه‌ی فعلی نیست.');
        }
        foreach ((array) ($offer['depends_on'] ?? []) as $dep) {
            if (! in_array($dep, $appliedCodes, true)) {
                throw new PatchFailure('DEPENDENCY_MISSING', "پیش‌نیاز {$dep} هنوز اعمال نشده است.");
            }
        }

        $this->assertTargetSafe();

        $probe = $this->target() . '/.gs-write-probe';
        if (@file_put_contents($probe, 'x') === false) {
            throw new PatchFailure('CODE_DIR_READONLY', 'اجازه‌ی نوشتن در پوشه‌ی برنامه وجود ندارد: ' . $this->target());
        }
        @unlink($probe);

        $this->ensureDir($this->env->dataPath);
        $dbSize = $this->env->dbFile !== null && is_file($this->env->dbFile) ? (int) filesize($this->env->dbFile) : 0;
        $need   = (int) ($offer['size'] ?? 0) * 3 + $dbSize * 2 + 20 * 1024 * 1024;
        $free   = @disk_free_space($this->env->dataPath);
        if ($free !== false && $free < $need) {
            throw new PatchFailure('DISK_SPACE', 'فضای دیسک برای دانلود و بکاپ کافی نیست.');
        }
    }

    /** جلوی بازنویسی سورس توسعه (مخزن git) را می‌گیرد، مگر با اجازه‌ی صریح */
    public function assertTargetSafe(): void
    {
        $target = $this->target();
        if (! is_dir($target)) {
            throw new PatchFailure('TARGET_MISSING', "مسیر هدف پچ وجود ندارد: {$target}");
        }
        if (! $this->env->allowGitTarget && file_exists($target . '/.git')) {
            throw new PatchFailure(
                'TARGET_IS_GIT_REPO',
                'مسیر هدف یک مخزن git است و پچ سورس واقعی شما را بازنویسی می‌کرد. برای تست، GS_PATCH_TARGET_PATH را روی یک کپی از پروژه بگذارید (یا GS_PATCH_ALLOW_GIT_TARGET=true).',
            );
        }
    }

    // ───────────────────────── نصب کامل ─────────────────────────

    /**
     * @param array<string,mixed>   $offer          یک عنصر از GET /patches (با download_url تازه)
     * @param Closure():list<string> $headers       هدرهای درخواست دانلود (توکن/اثرانگشت)
     * @param list<string>          $appliedCodes
     * @return array{manifest:array<string,mixed>, files:array<string,string>, backup:string, sql:list<string>, requires_restart:bool, version_after:string}
     */
    public function install(array $offer, Closure $headers, string $currentVersion, array $appliedCodes): array
    {
        $code = (string) $offer['patch_code'];
        $this->env->report('preflight', 2, 'بررسی پیش‌نیازها');
        $this->preflight($offer, $currentVersion, $appliedCodes);

        $zipPath = $this->fetch($offer, $headers);

        $this->env->report('verifying', 56, 'بررسی امضا و صحت بسته');
        [$manifest, $zip] = $this->verify($zipPath, $offer);

        $staging = $this->env->dataPath . "/staging/{$code}";
        Util::rrmdir($staging);
        $this->env->report('staging', 64, 'استخراج بسته');
        try {
            $this->stage($zip, $manifest, $staging);
        } finally {
            $zip->close();
        }

        $this->env->report('applying', 70, 'ایجاد بکاپ');
        $backup  = $this->backup($code, $manifest, $currentVersion);
        $touched = true;
        $dbCommitted = false;
        $scripts = [];

        try {
            $this->env->report('files', 78, 'جایگزینی فایل‌ها');
            $changed = $this->applyFiles($manifest, $staging);

            $this->env->report('lint', 84, 'بررسی سینتکس PHP');
            $this->lintPhp($changed);

            $this->env->report('sql', 90, 'به‌روزرسانی ساختار دیتابیس');
            $scripts = $this->applySql($code, $manifest, $staging, $dbCommitted);

            $this->env->report('health', 95, 'بررسی سلامت برنامه');
            if ($this->env->healthCheck !== null) {
                ($this->env->healthCheck)();
            }
        } catch (PatchFailure $e) {
            $e->touched    = $touched;
            $e->rolledBack = $this->restore($backup, $dbCommitted);
            throw $e;
        } catch (\Throwable $e) {
            $f             = new PatchFailure('UNEXPECTED', $e->getMessage(), $touched);
            $f->rolledBack = $this->restore($backup, $dbCommitted);
            throw $f;
        }

        $files = [];
        foreach ($manifest['files'] ?? [] as $f) {
            if ($f['action'] !== 'delete') {
                $files[(string) $f['path']] = strtolower((string) $f['sha256']);
            }
        }

        Util::rrmdir($staging);
        @unlink($zipPath);
        $this->pruneBackups($backup);
        $this->env->report('done', 100, 'پایان');

        return [
            'manifest'         => $manifest,
            'files'            => $files,
            'backup'           => $backup,
            'sql'              => $scripts,
            'requires_restart' => (bool) ($manifest['requires_restart'] ?? false),
            'version_after'     => (string) $manifest['to_version'],
        ];
    }

    // ───────────────────────── مراحل ─────────────────────────

    private function fetch(array $offer, Closure $headers): string
    {
        $dir = $this->env->dataPath . '/downloads';
        $this->ensureDir($dir);
        $dest = $dir . '/' . $offer['patch_code'] . '.zip';

        if (is_file($dest) && hash_file('sha256', $dest) === strtolower((string) $offer['sha256'])) {
            $this->env->report('downloading', 50, 'فایل قبلاً دانلود شده است');

            return $dest;
        }
        @unlink($dest);

        $this->env->report('downloading', 5, 'دانلود بسته');
        $last = 0.0;
        $size = $this->downloader->download(
            (string) $offer['download_url'],
            $dest,
            (int) $offer['size'],
            $headers(),
            function (int $done, int $total) use (&$last): void {
                $now = microtime(true);
                if ($now - $last < 0.7 || $total <= 0) {
                    return;
                }
                $last = $now;
                $this->env->report('downloading', 5 + (int) (45 * $done / $total), 'دانلود بسته');
            },
        );

        if ($size !== (int) $offer['size']) {
            @unlink($dest);
            throw new PatchFailure('SIZE_MISMATCH', "حجم فایل دانلودشده {$size} است ولی {$offer['size']} انتظار می‌رفت.");
        }
        if (hash_file('sha256', $dest) !== strtolower((string) $offer['sha256'])) {
            @unlink($dest);
            throw new PatchFailure('HASH_MISMATCH', 'sha256 فایل دانلودشده با مقدار سرور نمی‌خواند.');
        }

        return $dest;
    }

    /** @return array{0:array<string,mixed>,1:ZipArchive} */
    private function verify(string $zipPath, array $offer): array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new PatchFailure('ZIP_INVALID', 'فایل zip قابل باز شدن نیست.');
        }

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                if ($bad = Util::unsafePath(rtrim($name, '/'))) {
                    throw new PatchFailure('ZIP_UNSAFE_ENTRY', $bad);
                }
                $zip->getExternalAttributesIndex($i, $opsys, $attr);
                if ($opsys === ZipArchive::OPSYS_UNIX && (($attr >> 16) & 0170000) === 0120000) {
                    throw new PatchFailure('ZIP_SYMLINK', "symlink مجاز نیست: {$name}");
                }
                if (! ($name === 'manifest.json' || str_starts_with($name, 'files/') || str_starts_with($name, 'sql/'))) {
                    throw new PatchFailure('ZIP_UNEXPECTED_ENTRY', "ورودی غیرمنتظره در بسته: {$name}");
                }
            }

            $raw      = $zip->getFromName('manifest.json');
            $manifest = $raw === false ? null : json_decode($raw, true);
            if (! is_array($manifest)) {
                throw new PatchFailure('MANIFEST_INVALID', 'manifest.json خوانده نشد.');
            }
            if (! Util::sameJson($manifest, $offer['manifest'] ?? [])) {
                throw new PatchFailure('MANIFEST_MISMATCH', 'manifest داخل بسته با manifest اعلام‌شده‌ی سرور یکی نیست.');
            }

            // امضا روی manifest.json خود zip (ترتیب اصلی کلیدها) بررسی می‌شود
            $payload = strtolower((string) $offer['sha256']) . '.' . Util::encodeJson($manifest);
            $pub     = ($this->env->keyResolver)((string) $offer['kid']);
            $sig     = Util::b64uDecode((string) $offer['signature']);
            if (strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES || ! sodium_crypto_sign_verify_detached($sig, $payload, $pub)) {
                throw new PatchFailure('SIGNATURE_INVALID', 'امضای Ed25519 پچ معتبر نیست؛ بسته اعمال نمی‌شود.');
            }

            foreach (['patch_id' => 'patch_code', 'to_version' => 'to_version', 'from_min' => 'from_min', 'from_max' => 'from_max'] as $mk => $ok) {
                if ((string) ($manifest[$mk] ?? '') !== (string) $offer[$ok]) {
                    throw new PatchFailure('MANIFEST_MISMATCH', "فیلد {$mk} در manifest با پیشنهاد سرور نمی‌خواند.");
                }
            }

            foreach ($manifest['files'] ?? [] as $f) {
                $p = (string) ($f['path'] ?? '');
                if ($bad = Util::unsafePath($p)) {
                    throw new PatchFailure('PATH_UNSAFE', $bad);
                }
                if (! in_array(explode('/', $p)[0], $this->env->allowedRoots, true)) {
                    throw new PatchFailure('PATH_NOT_ALLOWED', "مسیر خارج از پوشه‌های مجاز: {$p}");
                }
                foreach ($this->env->forbiddenPatterns as $re) {
                    if (preg_match($re, $p) === 1) {
                        throw new PatchFailure('PATH_FORBIDDEN', "پچ حق دست‌زدن به این مسیر را ندارد: {$p}");
                    }
                }
                if (! in_array($f['action'] ?? '', ['add', 'replace', 'delete'], true)) {
                    throw new PatchFailure('MANIFEST_INVALID', "action نامعتبر برای {$p}");
                }
                if ($f['action'] !== 'delete' && $zip->locateName('files/' . $p) === false) {
                    throw new PatchFailure('FILE_MISSING_IN_ZIP', "فایل {$p} در بسته نیست.");
                }
            }

            foreach ($manifest['sql_scripts'] ?? [] as $s) {
                $file = (string) ($s['file'] ?? '');
                if ($bad = Util::unsafePath($file)) {
                    throw new PatchFailure('PATH_UNSAFE', $bad);
                }
                $sql = $zip->getFromName($file);
                if ($sql === false) {
                    throw new PatchFailure('SQL_MISSING_IN_ZIP', "اسکریپت {$file} در بسته نیست.");
                }
                if (hash('sha256', $sql) !== strtolower((string) $s['checksum'])) {
                    throw new PatchFailure('SQL_CHECKSUM', "checksum اسکریپت {$file} نمی‌خواند.");
                }
                $g = SqlGuard::inspect($sql);
                if ($g['forbidden']) {
                    throw new PatchFailure('SQL_FORBIDDEN', "اسکریپت {$file} دستور ممنوع دارد: " . implode('، ', $g['forbidden']));
                }
                if ($g['destructive'] && empty($s['destructive'])) {
                    throw new PatchFailure('SQL_DESTRUCTIVE', "اسکریپت {$file} دستور مخرب دارد (" . implode('، ', $g['destructive']) . ') ولی در manifest با destructive=true تأیید نشده است.');
                }
            }
        } catch (\Throwable $e) {
            $zip->close();
            throw $e;
        }

        return [$manifest, $zip];
    }

    private function stage(ZipArchive $zip, array $manifest, string $staging): void
    {
        $this->ensureDir($staging);
        foreach ($manifest['files'] ?? [] as $f) {
            if ($f['action'] === 'delete') {
                continue;
            }
            $dest = "{$staging}/files/{$f['path']}";
            $this->ensureDir(dirname($dest));
            $in  = $zip->getStream('files/' . $f['path']);
            $out = fopen($dest, 'wb');
            $ctx = hash_init('sha256');
            $size = 0;
            while ($in !== false && ! feof($in)) {
                $buf = (string) fread($in, 1 << 20);
                hash_update($ctx, $buf);
                $size += strlen($buf);
                fwrite($out, $buf);
            }
            if ($in !== false) {
                fclose($in);
            }
            fclose($out);
            if (hash_final($ctx) !== strtolower((string) $f['sha256'])) {
                throw new PatchFailure('FILE_HASH', "sha256 فایل {$f['path']} با manifest نمی‌خواند.");
            }
            if (isset($f['size']) && (int) $f['size'] !== $size) {
                throw new PatchFailure('FILE_SIZE', "حجم فایل {$f['path']} با manifest نمی‌خواند.");
            }
        }
        foreach ($manifest['sql_scripts'] ?? [] as $s) {
            $dest = "{$staging}/{$s['file']}";
            $this->ensureDir(dirname($dest));
            file_put_contents($dest, $zip->getFromName($s['file']));
        }
    }

    private function backup(string $code, array $manifest, string $versionBefore): string
    {
        $dir = $this->env->dataPath . '/backups/' . gmdate('Ymd-His') . "-{$code}";
        $this->ensureDir("{$dir}/files");
        $meta = ['patch' => $code, 'version_before' => $versionBefore, 'replaced' => [], 'added' => [], 'deleted' => [], 'db' => false, 'created_at' => gmdate('c')];

        foreach ($manifest['files'] ?? [] as $f) {
            $src = $this->target() . '/' . $f['path'];
            if (is_file($src)) {
                $this->ensureDir(dirname("{$dir}/files/{$f['path']}"));
                if (! copy($src, "{$dir}/files/{$f['path']}")) {
                    throw new PatchFailure('BACKUP_FAILED', "بکاپ گرفتن از {$f['path']} ممکن نشد.");
                }
                $meta['replaced'][] = $f['path'];
            } elseif ($f['action'] !== 'delete') {
                $meta['added'][] = $f['path'];
            }
        }

        if (! empty($manifest['sql_scripts'])) {
            $target = "{$dir}/database.sqlite";
            $pdo    = ($this->env->pdo)();
            try {
                $pdo->exec('VACUUM INTO ' . $pdo->quote($target));
            } catch (\Throwable) {
                if ($this->env->dbFile === null || ! @copy($this->env->dbFile, $target)) {
                    throw new PatchFailure('BACKUP_FAILED', 'بکاپ گرفتن از دیتابیس ممکن نشد؛ پچ اعمال نمی‌شود.');
                }
            }
            $meta['db'] = true;
        }

        file_put_contents("{$dir}/meta.json", json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $dir;
    }

    /** @return list<string> */
    private function applyFiles(array $manifest, string $staging): array
    {
        $changed = [];
        foreach ($manifest['files'] ?? [] as $f) {
            $dest = $this->target() . '/' . $f['path'];
            if ($f['action'] === 'delete') {
                if (is_file($dest) && ! @unlink($dest)) {
                    throw new PatchFailure('FILE_WRITE', "حذف فایل ممکن نشد: {$f['path']}", true);
                }
                continue;
            }
            $this->ensureDir(dirname($dest));
            $tmp = $dest . '.gs-new';
            if (! @copy("{$staging}/files/{$f['path']}", $tmp) || ! @rename($tmp, $dest)) {
                @unlink($tmp);
                throw new PatchFailure('FILE_WRITE', "نوشتن فایل ممکن نشد: {$f['path']} (قفل‌بودن فایل یا نبودن دسترسی؟)", true);
            }
            $changed[] = $dest;
        }

        return $changed;
    }

    /** @param list<string> $files */
    private function lintPhp(array $files): void
    {
        foreach ($files as $f) {
            if (! str_ends_with($f, '.php')) {
                continue;
            }
            $out = [];
            exec(escapeshellarg($this->env->phpBinary) . ' -l ' . escapeshellarg($f) . ' 2>&1', $out, $rc);
            if ($rc !== 0) {
                throw new PatchFailure('PHP_SYNTAX', 'خطای سینتکس در فایل پچ‌شده ' . basename($f) . ': ' . trim(implode(' ', $out)), true);
            }
        }
    }

    /** @return list<string> */
    private function applySql(string $code, array $manifest, string $staging, bool &$dbCommitted): array
    {
        $scripts = $manifest['sql_scripts'] ?? [];
        if ($scripts === []) {
            return [];
        }
        usort($scripts, static fn ($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

        $pdo = ($this->env->pdo)();
        self::ensureLedger($pdo);
        $before = self::rowCounts($pdo);
        $fkWas  = (int) $pdo->query('PRAGMA foreign_keys')->fetchColumn();
        $ran    = [];
        $allowLoss = false;

        $pdo->exec('PRAGMA foreign_keys = OFF'); // داخل تراکنش قابل تغییر نیست
        $pdo->beginTransaction();
        try {
            foreach ($scripts as $s) {
                $q = $pdo->prepare('SELECT 1 FROM schema_patches WHERE patch_code = ? AND script = ? AND checksum = ?');
                $q->execute([$code, $s['file'], $s['checksum']]);
                if ($q->fetchColumn()) {
                    continue; // idempotent
                }
                $pdo->exec((string) file_get_contents("{$staging}/{$s['file']}"));
                $pdo->prepare('INSERT INTO schema_patches(patch_code, script, checksum, applied_at) VALUES (?,?,?,?)')
                    ->execute([$code, $s['file'], $s['checksum'], gmdate('c')]);
                $ran[]     = (string) $s['file'];
                $allowLoss = $allowLoss || ! empty($s['allow_row_loss']);
            }

            $fk = $pdo->query('PRAGMA foreign_key_check')->fetchAll(PDO::FETCH_ASSOC);
            if ($fk !== []) {
                throw new PatchFailure('FK_VIOLATION', 'پس از اجرای SQL کلید خارجی نقض شده است: ' . json_encode(array_slice($fk, 0, 3)), true);
            }
            $qc = $pdo->query('PRAGMA quick_check')->fetchColumn();
            if ($qc !== 'ok') {
                throw new PatchFailure('DB_INTEGRITY', "quick_check ناموفق: {$qc}", true);
            }

            // «دیتای کاربر دست نخورد»: هیچ جدول قبلی نباید ردیف از دست بدهد
            $after = self::rowCounts($pdo);
            foreach ($before as $t => $n) {
                if ($t === 'schema_patches' || $allowLoss || ! array_key_exists($t, $after)) {
                    continue;
                }
                if ($after[$t] < $n) {
                    throw new PatchFailure('ROW_LOSS', "تعداد ردیف‌های جدول {$t} از {$n} به {$after[$t]} کاهش یافت؛ تغییرات برگردانده شد.", true);
                }
            }
            $pdo->commit();
            $dbCommitted = $ran !== [];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $pdo->exec('PRAGMA foreign_keys = ' . ($fkWas ? 'ON' : 'OFF'));
            if ($e instanceof PatchFailure) {
                throw $e;
            }
            throw new PatchFailure('SQL_ERROR', 'اجرای SQL ناموفق: ' . $e->getMessage(), true);
        }
        $pdo->exec('PRAGMA foreign_keys = ' . ($fkWas ? 'ON' : 'OFF'));

        return $ran;
    }

    // ───────────────────────── بازگردانی ─────────────────────────

    private function restore(string $backupDir, bool $restoreDb): bool
    {
        try {
            $this->restoreFiles($backupDir);
            if ($restoreDb) {
                $this->restoreDb($backupDir);
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** فایل‌ها را به حالت قبل از پچ برمی‌گرداند (برای rollback دستی هم استفاده می‌شود) */
    public function restoreFiles(string $backupDir): void
    {
        $meta = json_decode((string) file_get_contents("{$backupDir}/meta.json"), true);
        if (! is_array($meta)) {
            throw new PatchFailure('BACKUP_MISSING', 'اطلاعات بکاپ پیدا نشد.');
        }
        foreach ($meta['replaced'] as $p) {
            $this->ensureDir(dirname($this->target() . "/{$p}"));
            copy("{$backupDir}/files/{$p}", $this->target() . "/{$p}");
        }
        foreach ($meta['added'] as $p) {
            $f = $this->target() . "/{$p}";
            if (is_file($f)) {
                @unlink($f);
            }
        }
    }

    private function restoreDb(string $backupDir): void
    {
        $meta = json_decode((string) file_get_contents("{$backupDir}/meta.json"), true);
        if (empty($meta['db']) || ! is_file("{$backupDir}/database.sqlite")) {
            return;
        }
        if ($this->env->dbFile === null) {
            throw new PatchFailure('RESTORE_FAILED', 'مسیر فایل دیتابیس برای بازگردانی مشخص نیست.');
        }
        if ($this->env->closeDb !== null) {
            ($this->env->closeDb)();
        }
        foreach (['-wal', '-shm', '-journal'] as $sfx) {
            @unlink($this->env->dbFile . $sfx);
        }
        if (! copy("{$backupDir}/database.sqlite", $this->env->dbFile)) {
            throw new PatchFailure('RESTORE_FAILED', 'بازگردانی فایل دیتابیس ناموفق بود.');
        }
    }

    private function pruneBackups(string $keepDir): void
    {
        $dirs = glob($this->env->dataPath . '/backups/*', GLOB_ONLYDIR) ?: [];
        sort($dirs);
        foreach (array_slice($dirs, 0, max(0, count($dirs) - $this->env->keepBackups)) as $d) {
            if ($d !== $keepDir) {
                Util::rrmdir($d);
            }
        }
    }

    // ───────────────────────── کمکی ─────────────────────────

    public static function ensureLedger(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_patches (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            patch_code TEXT NOT NULL,
            script TEXT NOT NULL,
            checksum TEXT NOT NULL,
            applied_at TEXT NOT NULL,
            UNIQUE(patch_code, script)
        )');
    }

    /** @return array<string,int> */
    public static function rowCounts(PDO $pdo): array
    {
        $out   = [];
        $names = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($names as $name) {
            $out[(string) $name] = (int) $pdo->query('SELECT COUNT(*) FROM "' . str_replace('"', '""', (string) $name) . '"')->fetchColumn();
        }

        return $out;
    }

    private function target(): string
    {
        return rtrim($this->env->targetPath, '/\\');
    }

    private function ensureDir(string $dir): void
    {
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new PatchFailure('FS_ERROR', "ساخت پوشه ممکن نشد: {$dir}");
        }
    }
}
