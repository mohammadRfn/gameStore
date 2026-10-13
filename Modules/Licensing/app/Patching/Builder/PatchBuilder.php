<?php

declare(strict_types=1);

namespace Modules\Licensing\Patching\Builder;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Modules\Licensing\Patching\Core\SqlGuard;
use ZipArchive;

/**
 * سازنده‌ی بسته‌ی پچ (سمت توسعه‌دهنده، نه مشتری).
 *
 * مبنا (base) = کپی فایل‌های نسخه‌ی منتشرشده؛ make تفاوت پروژه با مبنا را به‌صورت zip
 * مطابق قرارداد Installer (manifest.json + files/<مسیر>) می‌سازد.
 * هیچ وابستگی‌ای به Laravel ندارد تا جداگانه قابل تست باشد.
 */
final class PatchBuilder
{
    /** پوشه‌هایی که پچ می‌تواند بسازد (فرانت‌سورس شامل نیست؛ بیلدشده‌اش در public/build است) */
    public const ROOTS = ['app', 'Modules', 'routes', 'config', 'database', 'lang', 'resources/views', 'public'];

    /** تغییر این فایل‌ها با پچ قابل انتقال نیست؛ فقط هشدار می‌دهیم */
    public const WATCH = ['composer.lock', 'composer.json'];

    private string $work;
    private string $base;
    private string $stateFile;

    public function __construct(string $work)
    {
        $this->work = rtrim($work, "\\/");
        $this->base = $this->work . '/base';
        $this->stateFile = $this->work . '/state.json';
    }

    public function workDir(): string
    {
        return $this->work;
    }

    /** @return array<string,mixed> */
    public function state(): array
    {
        if (! is_file($this->stateFile)) {
            return [];
        }

        return json_decode((string) file_get_contents($this->stateFile), true) ?: [];
    }

    /** ثبت نسخه‌ی منتشرشده به‌عنوان مبنا. @return int تعداد فایل‌های ثبت‌شده */
    public function init(string $from, string $version): int
    {
        if (! preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            throw new RuntimeException('نسخه باید semver باشد، مثل 1.0.0');
        }
        $from = rtrim($from, "\\/");
        if (! is_dir($from)) {
            throw new RuntimeException("مسیر --from پیدا نشد: $from");
        }
        $n = $this->snapshot($from);
        $this->saveState(['version' => $version, 'created' => date('c')]);

        return $n;
    }

    /**
     * ساخت zip پچ از تفاوت پروژه با مبنا.
     *
     * @param  array{desc?:string,to?:string,from_max?:string,id?:string,restart?:bool}  $o
     * @return array<string,mixed>
     */
    public function make(string $project, string $title, array $o = []): array
    {
        $project = rtrim($project, "\\/");
        $state = $this->state();
        if (! is_dir($this->base) || ! isset($state['version'])) {
            throw new RuntimeException('مبنا ثبت نشده؛ اول init را اجرا کن.');
        }
        if (trim($title) === '') {
            throw new RuntimeException('عنوان پچ الزامی است.');
        }

        $from = (string) $state['version'];
        $to = $o['to'] ?? $this->bump($from);
        $fromMax = $o['from_max'] ?? $from;
        $id = $o['id'] ?? ('p-' . $to);
        $semver = '/^\d+\.\d+\.\d+$/';
        if (! preg_match($semver, $to) || ! preg_match($semver, $fromMax)) {
            throw new RuntimeException('نسخه‌ها باید semver باشند.');
        }
        if (! preg_match('/^[A-Za-z0-9._\-]+$/', $id)) {
            throw new RuntimeException('شناسه‌ی پچ فقط می‌تواند حروف انگلیسی، عدد، نقطه، خط تیره و زیرخط داشته باشد.');
        }
        if (version_compare($to, $from, '<=')) {
            throw new RuntimeException("نسخه‌ی مقصد ($to) باید از نسخه‌ی مبنا ($from) بزرگ‌تر باشد.");
        }

        $old = $this->scan($this->base);
        $new = $this->scan($project);
        $files = [];
        $deletes = [];
        $stat = ['add' => 0, 'replace' => 0, 'delete' => 0];

        foreach ($new as $rel => $path) {
            $sha = (string) hash_file('sha256', $path);
            if (! isset($old[$rel])) {
                $act = 'add';
            } elseif (hash_file('sha256', $old[$rel]) !== $sha) {
                $act = 'replace';
            } else {
                continue;
            }
            $stat[$act]++;
            $files[] = ['path' => $rel, 'action' => $act, 'sha256' => $sha, 'size' => (int) filesize($path), '_src' => $path];
        }
        foreach ($old as $rel => $_) {
            if (! isset($new[$rel])) {
                $stat['delete']++;
                $deletes[] = ['path' => $rel, 'action' => 'delete'];
            }
        }
        // اسکریپت‌های SQL اختیاری (پوشه‌ی --sql): هر *.sql به ترتیب نام، داخل تراکنش روی دیتابیس مشتری اجرا می‌شود
        $sqlScripts = [];
        $sqlSources = [];
        if (! empty($o['sql'])) {
            foreach ($this->readSqlDir((string) $o['sql']) as $i => [$name, $content]) {
                $g = SqlGuard::inspect($content);
                if ($g['forbidden']) {
                    throw new RuntimeException("اسکریپت {$name} دستور ممنوع دارد: " . implode('، ', $g['forbidden']));
                }
                if ($g['destructive']) {
                    throw new RuntimeException("اسکریپت {$name} دستور مخرب دارد (" . implode('، ', $g['destructive']) . ') و این ابزار آن را خودکار تأیید نمی‌کند.');
                }
                $sqlScripts[] = ['file' => 'sql/' . $name, 'checksum' => hash('sha256', $content), 'order' => $i + 1];
                $sqlSources['sql/' . $name] = $content;
            }
        }

        if ($stat['add'] + $stat['replace'] + $stat['delete'] === 0 && $sqlScripts === []) {
            throw new RuntimeException('هیچ تفاوتی با نسخه‌ی مبنا نیست؛ پچی لازم نیست (بیلد فرانت را هم زده‌ای؟).');
        }

        // manifest.json بیلد آخر از اضافه/جایگزین‌ها؛ حذف‌ها در انتها
        $weight = static fn (array $e): int => $e['path'] === 'public/build/manifest.json' ? 1 : 0;
        usort($files, static fn (array $a, array $b): int => [$weight($a), $a['path']] <=> [$weight($b), $b['path']]);

        $entries = [];
        foreach (array_merge($files, $deletes) as $e) {
            if (str_contains($e['path'], ':') || str_contains($e['path'], '..')) {
                throw new RuntimeException('مسیر نامعتبر: ' . $e['path']);
            }
            $entries[] = array_diff_key($e, ['_src' => 1]);
        }

        $manifest = [
            'patch_id' => $id,
            'title' => $title,
            'description' => $o['desc'] ?? '',
            'type' => $sqlScripts === [] ? 'files' : ($entries === [] ? 'database' : 'both'),
            'from_min' => $from,
            'from_max' => $fromMax,
            'to_version' => $to,
            'requires_restart' => $o['restart'] ?? true,
            'files' => $entries,
        ];
        if ($sqlScripts !== []) {
            $manifest['sql_scripts'] = $sqlScripts;
        }

        $outDir = $this->work . '/out';
        if (! is_dir($outDir) && ! mkdir($outDir, 0777, true)) {
            throw new RuntimeException("ساخت پوشه‌ی خروجی ممکن نشد: $outDir");
        }
        $zipPath = "$outDir/$id.zip";
        file_put_contents("$outDir/$id.changes.txt", implode("\n", array_merge(
            array_map(static fn (array $e): string => "[{$e['action']}] {$e['path']}", $entries),
            array_map(static fn (array $e): string => "[sql] {$e['file']}", $sqlScripts)
        )) . "\n");
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('ساخت zip ممکن نشد.');
        }
        $zip->addFromString('manifest.json', (string) json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        foreach ($files as $f) {
            $zip->addFile($f['_src'], 'files/' . $f['path']);
        }
        foreach ($sqlSources as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        $this->verifyZip($zipPath);

        $state['pending'] = ['id' => $id, 'to' => $to];
        $this->saveState($state);

        $warnings = [];
        foreach (self::WATCH as $w) {
            $b = $this->base . '/_watch_' . $w;
            if (is_file($b) && is_file("$project/$w") && hash_file('sha256', $b) !== hash_file('sha256', "$project/$w")) {
                $warnings[] = "$w نسبت به مبنا تغییر کرده؛ تغییر وابستگی‌ها با پچ منتقل نمی‌شود و به نصب‌کننده‌ی کامل نیاز دارد.";
            }
        }

        return [
            'zip' => $zipPath,
            'id' => $id,
            'from' => $from,
            'from_max' => $fromMax,
            'to' => $to,
            'restart' => (bool) $manifest['requires_restart'],
            'stat' => $stat,
            'sql' => array_column($sqlScripts, 'file'),
            'code' => array_values(array_filter($entries, static fn (array $e): bool => ! str_starts_with($e['path'], 'public/build/'))),
            'warnings' => $warnings,
            'size' => (int) filesize($zipPath),
            'sha256' => (string) hash_file('sha256', $zipPath),
        ];
    }

    /** بعد از انتشار موفق: مبنا را روی وضعیت فعلی پروژه می‌گذارد. @return array{files:int,version:string} */
    public function release(string $project): array
    {
        $state = $this->state();
        if (! isset($state['pending'])) {
            throw new RuntimeException('پچ در انتظاری نیست؛ اول make را اجرا کن.');
        }
        $n = $this->snapshot(rtrim($project, "\\/"));
        $v = (string) $state['pending']['to'];
        $this->saveState(['version' => $v, 'released_patch' => $state['pending']['id'], 'created' => date('c')]);

        return ['files' => $n, 'version' => $v];
    }

    // ───────────────────────── داخلی ─────────────────────────

    private function verifyZip(string $zipPath): void
    {
        $z = new ZipArchive();
        if ($z->open($zipPath) !== true) {
            throw new RuntimeException('zip ساخته‌شده باز نمی‌شود.');
        }
        $m = json_decode((string) $z->getFromName('manifest.json'), true);
        if (! is_array($m)) {
            throw new RuntimeException('manifest.json داخل zip خوانده نشد.');
        }
        for ($i = 0; $i < $z->numFiles; $i++) {
            $n = (string) $z->getNameIndex($i);
            if (! ($n === 'manifest.json' || str_starts_with($n, 'files/') || str_starts_with($n, 'sql/')) || str_contains($n, '\\')) {
                throw new RuntimeException("ورودی نامعتبر در zip: $n");
            }
        }
        foreach ($m['files'] as $e) {
            if ($e['action'] === 'delete') {
                continue;
            }
            $d = $z->getFromName('files/' . $e['path']);
            if ($d === false || hash('sha256', $d) !== $e['sha256'] || strlen($d) !== $e['size']) {
                throw new RuntimeException('عدم تطابق هش/حجم در zip: ' . $e['path']);
            }
        }
        foreach ($m['sql_scripts'] ?? [] as $sc) {
            $d = $z->getFromName((string) $sc['file']);
            if ($d === false || hash('sha256', $d) !== strtolower((string) $sc['checksum'])) {
                throw new RuntimeException('عدم تطابق checksum اسکریپت SQL در zip: ' . $sc['file']);
            }
        }
        $z->close();
    }

    /**
     * خواندن *.sql از یک پوشه به ترتیب نام.
     *
     * @return list<array{0:string,1:string}> [نام فایل، محتوا]
     */
    private function readSqlDir(string $dir): array
    {
        $dir = rtrim($dir, "\\/");
        if (! is_dir($dir)) {
            throw new RuntimeException("پوشه‌ی SQL پیدا نشد: $dir");
        }
        $found = glob($dir . '/*.sql') ?: [];
        sort($found, SORT_STRING);
        if ($found === []) {
            throw new RuntimeException("در $dir هیچ فایل .sql پیدا نشد.");
        }
        $out = [];
        foreach ($found as $path) {
            $name = basename($path);
            if (! preg_match('/^[A-Za-z0-9._\-]+\.sql$/', $name)) {
                throw new RuntimeException("نام فایل SQL نامعتبر است: $name (فقط حروف انگلیسی، عدد، نقطه، خط تیره و زیرخط).");
            }
            $content = (string) file_get_contents($path);
            if (trim($content) === '') {
                throw new RuntimeException("فایل SQL خالی است: $name");
            }
            $out[] = [$name, $content];
        }

        return $out;
    }

    private function snapshot(string $from): int
    {
        $files = $this->scan($from);
        if ($files === []) {
            throw new RuntimeException("در $from هیچ‌کدام از پوشه‌های app/Modules/routes/... پیدا نشد؛ مسیر درست است؟");
        }
        $this->rrmdir($this->base);
        if (! is_dir($this->base) && ! mkdir($this->base, 0777, true)) {
            throw new RuntimeException('ساخت پوشه‌ی مبنا ممکن نشد: ' . $this->base);
        }
        foreach ($files as $rel => $src) {
            $to = $this->base . '/' . $rel;
            if (! is_dir(dirname($to)) && ! mkdir(dirname($to), 0777, true)) {
                throw new RuntimeException('ساخت پوشه ممکن نشد: ' . dirname($to));
            }
            if (! copy($src, $to)) {
                throw new RuntimeException("کپی نشد: $rel");
            }
        }
        foreach (self::WATCH as $w) {
            if (is_file("$from/$w")) {
                copy("$from/$w", $this->base . '/_watch_' . $w);
            }
        }

        return count($files);
    }

    /** @return array<string,string> مسیر نسبی (با /) => مسیر واقعی */
    private function scan(string $root): array
    {
        $out = [];
        foreach (self::ROOTS as $r) {
            $dir = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $r);
            if (! is_dir($dir)) {
                continue;
            }
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if (! $f->isFile()) {
                    continue;
                }
                $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
                if (! $this->skip($rel)) {
                    $out[$rel] = $f->getPathname();
                }
            }
        }
        ksort($out);

        return $out;
    }

    private function skip(string $rel): bool
    {
        $n = '/' . $rel . '/';
        // مواردی که NativePHP موقع بسته‌بندی حذف می‌کند (مثل tests و .gitkeep) نباید در مقایسه بیایند
        foreach (['/node_modules/', '/.git/', '/vendor/', '/storage/', '/bootstrap/cache/', '/tests/'] as $seg) {
            if (str_contains($n, $seg)) {
                return true;
            }
        }
        $base = basename($rel);
        if (in_array($base, ['.DS_Store', 'Thumbs.db', 'hot'], true) || str_starts_with($base, '.git')) {
            return true;
        }
        if (preg_match('/\.(log|sqlite3?|db|sqlite-wal|sqlite-shm|sqlite-journal)$/i', $base)) {
            return true;
        }

        return (bool) preg_match('#(^|/)\.env($|\.)#i', $rel);
    }

    private function rrmdir(string $d): void
    {
        if (! is_dir($d)) {
            return;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($d);
    }

    private function bump(string $v): string
    {
        [$a, $b, $c] = array_map('intval', explode('.', $v));

        return "$a.$b." . ($c + 1);
    }

    /** @param array<string,mixed> $s */
    private function saveState(array $s): void
    {
        if (! is_dir($this->work) && ! mkdir($this->work, 0777, true)) {
            throw new RuntimeException('ساخت پوشه‌ی کاری ممکن نشد: ' . $this->work);
        }
        file_put_contents($this->stateFile, json_encode($s, JSON_PRETTY_PRINT));
    }
}