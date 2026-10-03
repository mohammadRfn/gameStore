<?php

declare(strict_types=1);

namespace Modules\SchemaManager\Schema;

use PDO;
use Throwable;

/**
 * ساخت و هم‌سطح‌سازی اسکیمای SQLite روی دستگاه مشتری (بدون وابستگی به Laravel).
 *
 * ورودی‌ها (در database/schema/ همراه نصب‌کننده):
 *   baseline.sql        اسکیمای کامل نسخه (خروجی schema:export)
 *   seed.sql            داده‌ی اولیه؛ فقط روی نصب تازه اجرا می‌شود (اختیاری)
 *   baseline.meta.json  اطلاعات baseline و فهرست upgrades شامل‌شده در آن (اختیاری)
 *   upgrades/NNNN_*.sql تغییرات غیرافزایشی (rename/drop/اصلاح داده) برای نصب‌های قدیمی
 *
 * منطق:
 *   ۱. baseline در یک دیتابیس حافظه‌ای ساخته می‌شود (مرجع). اگر خراب بود، به دیتابیس واقعی دست نمی‌زنیم.
 *   ۲. نصب تازه (هیچ‌کدام از جدول‌های مرجع وجود ندارد): ساخت همه‌چیز + seed + ثبت upgrades به‌عنوان «شامل‌شده».
 *   ۳. نصب موجود: ابتدا upgrades ثبت‌نشده (هرکدام در تراکنش)، سپس هم‌سطح‌سازی افزایشی:
 *      جدول/ایندکس/تریگر/ویوی ناموجود ساخته می‌شود و ستون ناموجود با ALTER ADD COLUMN اضافه می‌شود.
 *      هرگز ستون یا جدول یا ردیفی حذف یا تغییر نمی‌کند.
 *   ۴. نتیجه در جدول schema_patches (patch_code = 'installer') ثبت می‌شود تا تکرار نشود.
 */
final class SchemaBootstrapper
{
    public const LEDGER_CODE = 'installer';

    /** @var list<string> */
    private array $warnings = [];

    /** @var list<string> */
    private array $createdTables = [];

    /** @var array<string,list<string>> */
    private array $addedColumns = [];

    /** @var list<string> */
    private array $upgradesRun = [];

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $schemaDir,
        private readonly ?string $backupDir = null,
    ) {}

    /**
     * @return array{status:string, fresh:bool, created_tables:list<string>, added_columns:array<string,list<string>>, upgrades:list<string>, warnings:list<string>, error:?string}
     */
    public function ensure(): array
    {
        $baselineFile = $this->schemaDir . '/baseline.sql';
        if (! is_file($baselineFile)) {
            return $this->report('no_baseline', false);
        }

        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // مسیر سریع (هر درخواست وب): اگر baseline و همه‌ی upgrades در ledger ثبت شده‌اند، کاری لازم نیست
        if ($this->isUpToDate($baselineFile)) {
            return $this->report('noop', false);
        }

        $baseline = (string) file_get_contents($baselineFile);
        $ref      = $this->buildReference($baseline);
        if ($ref === null) {
            return $this->report('error', false, 'baseline.sql روی دیتابیس مرجع اجرا نشد؛ به دیتابیس برنامه دست نخورد.');
        }
        $this->ensureLedger();

        $refTables = $this->tables($ref);
        $existing  = $this->tables($this->pdo);
        $fresh     = array_intersect($refTables, $existing) === [];
        $checksum  = hash('sha256', $baseline);
        $ledgerKey = 'baseline@' . substr($checksum, 0, 16);

        try {
            $this->pdo->exec('PRAGMA foreign_keys = OFF');

            if ($fresh) {
                $this->applyFresh($ref, $baseline, $checksum, $ledgerKey);
            } else {
                $this->applyUpgrades();
                if (! $this->isRecorded($ledgerKey)) {
                    $this->reconcile($ref);
                    $this->record($ledgerKey, $checksum);
                }
            }
        } catch (Throwable $e) {
            $this->pdo->exec('PRAGMA foreign_keys = ON');

            return $this->report('error', $fresh, $e->getMessage());
        }
        $this->pdo->exec('PRAGMA foreign_keys = ON');

        $changed = $fresh || $this->createdTables || $this->addedColumns || $this->upgradesRun;

        return $this->report($fresh ? 'fresh' : ($changed ? 'upgraded' : 'ok'), $fresh);
    }

    // ───────────────────────── نصب تازه ─────────────────────────

    private function applyFresh(PDO $ref, string $baseline, string $checksum, string $ledgerKey): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec($baseline);
            $this->createdTables = $this->tables($ref);

            $seed = $this->schemaDir . '/seed.sql';
            if (is_file($seed)) {
                $this->pdo->exec((string) file_get_contents($seed));
            }

            $this->record($ledgerKey, $checksum);
            foreach ($this->upgradeFiles() as $f) {
                $this->record(basename($f), hash_file('sha256', $f) ?: '', note: 'included-in-baseline');
            }

            $fk = $this->pdo->query('PRAGMA foreign_key_check')->fetchAll(PDO::FETCH_ASSOC);
            if ($fk !== []) {
                throw new \RuntimeException('کلید خارجی نقض‌شده پس از ساخت اسکیمای تازه: ' . json_encode(array_slice($fk, 0, 3)));
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    // ───────────────────────── upgrades ─────────────────────────

    private function applyUpgrades(): void
    {
        $pending = [];
        foreach ($this->upgradeFiles() as $f) {
            if (! $this->isRecorded(basename($f))) {
                $pending[] = $f;
            }
        }
        if ($pending === []) {
            return;
        }

        $this->backup();

        foreach ($pending as $file) {
            $name = basename($file);
            $sql  = (string) file_get_contents($file);
            $sum  = hash('sha256', $sql);

            // «-- @skip-if-patch: code1, code2» → اگر آن پچ(ها) از طریق سرور اعمال شده‌اند، تکرار نشود
            if (preg_match('/^--\s*@skip-if-patch:\s*(.+)$/mi', $sql, $m) === 1) {
                $codes = array_filter(array_map('trim', explode(',', $m[1])));
                $q     = $this->pdo->prepare('SELECT 1 FROM schema_patches WHERE patch_code = ? LIMIT 1');
                foreach ($codes as $c) {
                    $q->execute([$c]);
                    if ($q->fetchColumn()) {
                        $this->record($name, $sum, note: 'skipped: patch ' . $c);
                        $this->warnings[] = "upgrade {$name} رد شد چون پچ {$c} همان تغییر را قبلاً اعمال کرده بود.";
                        continue 2;
                    }
                }
            }

            $this->pdo->beginTransaction();
            try {
                $this->pdo->exec($sql);
                $this->record($name, $sum);
                $fk = $this->pdo->query('PRAGMA foreign_key_check')->fetchAll(PDO::FETCH_ASSOC);
                if ($fk !== []) {
                    throw new \RuntimeException('نقض کلید خارجی: ' . json_encode(array_slice($fk, 0, 3)));
                }
                if (($qc = $this->pdo->query('PRAGMA quick_check')->fetchColumn()) !== 'ok') {
                    throw new \RuntimeException("quick_check ناموفق: {$qc}");
                }
                $this->pdo->commit();
                $this->upgradesRun[] = $name;
            } catch (Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw new \RuntimeException("upgrade {$name} ناموفق بود و برگردانده شد: " . $e->getMessage(), 0, $e);
            }
        }
    }

    // ───────────────────────── هم‌سطح‌سازی افزایشی ─────────────────────────

    private function reconcile(PDO $ref): void
    {
        $this->pdo->beginTransaction();
        try {
            $refObjects = $ref->query("SELECT type, name, tbl_name, sql FROM sqlite_master WHERE sql IS NOT NULL AND name NOT LIKE 'sqlite_%' ORDER BY CASE type WHEN 'table' THEN 0 WHEN 'index' THEN 1 WHEN 'trigger' THEN 2 ELSE 3 END, name")->fetchAll(PDO::FETCH_ASSOC);
            $have       = [];
            foreach ($this->pdo->query("SELECT type, name FROM sqlite_master WHERE name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_ASSOC) as $o) {
                $have[$o['type'] . ':' . $o['name']] = true;
            }

            foreach ($refObjects as $o) {
                $key = $o['type'] . ':' . $o['name'];
                if ($o['type'] === 'table') {
                    if (! isset($have[$key])) {
                        $this->pdo->exec($this->ifNotExists((string) $o['sql']));
                        $this->createdTables[] = (string) $o['name'];
                    } else {
                        $this->addMissingColumns($ref, (string) $o['name']);
                    }
                } elseif (! isset($have[$key])) {
                    if (! isset($have['table:' . $o['tbl_name']]) && $o['type'] === 'index') {
                        continue;
                    }
                    try {
                        $this->pdo->exec($this->ifNotExists((string) $o['sql']));
                    } catch (Throwable $e) {
                        // مثلاً ایندکس یکتا روی داده‌ی تکراریِ موجود؛ نباید کل هم‌سطح‌سازی را بیندازد
                        $this->warnings[] = "ساخت {$o['type']} {$o['name']} ممکن نشد: " . $e->getMessage();
                    }
                }
            }

            $this->reconcileUniqueConstraints($ref);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function addMissingColumns(PDO $ref, string $table): void
    {
        $have = [];
        foreach ($this->pdo->query('PRAGMA table_info(' . $this->q($table) . ')')->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $have[strtolower((string) $c['name'])] = true;
        }

        foreach ($ref->query('PRAGMA table_xinfo(' . $this->q($table) . ')')->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $name = (string) $c['name'];
            if (isset($have[strtolower($name)])) {
                continue;
            }
            if ((int) ($c['hidden'] ?? 0) !== 0) {
                continue; // ستون generated/hidden: خودکار نمی‌سازیم
            }

            $type    = trim((string) $c['type']);
            $default = $c['dflt_value'];
            $def     = $this->q($name) . ($type !== '' ? ' ' . $type : '');
            if ($default !== null) {
                $def .= ' DEFAULT ' . $default;
                if ((int) $c['notnull'] === 1) {
                    $def .= ' NOT NULL';
                }
            } elseif ((int) $c['notnull'] === 1) {
                $this->warnings[] = "ستون {$table}.{$name} در baseline NOT NULL بدون مقدار پیش‌فرض است؛ به‌صورت nullable اضافه شد. برای سخت‌گیری یک upgrade بنویسید.";
            }
            if ((int) $c['pk'] > 0) {
                $this->warnings[] = "ستون {$table}.{$name} کلید اصلی است و با ALTER قابل افزودن نیست؛ نادیده گرفته شد.";
                continue;
            }

            $this->pdo->exec('ALTER TABLE ' . $this->q($table) . ' ADD COLUMN ' . $def);
            $this->addedColumns[$table][] = $name;
        }
    }

    /** قید UNIQUE ستونی (در CREATE TABLE) با ALTER ساخته نمی‌شود؛ معادل آن ایندکس یکتا ساخته می‌شود */
    private function reconcileUniqueConstraints(PDO $ref): void
    {
        foreach ($this->addedColumns as $table => $cols) {
            foreach ($ref->query('PRAGMA index_list(' . $this->q($table) . ')')->fetchAll(PDO::FETCH_ASSOC) as $idx) {
                if ((int) $idx['unique'] !== 1 || ! in_array($idx['origin'], ['u', 'c'], true)) {
                    continue;
                }
                $icols = array_map(
                    static fn ($r) => (string) $r['name'],
                    $ref->query('PRAGMA index_info(' . $this->q((string) $idx['name']) . ')')->fetchAll(PDO::FETCH_ASSOC),
                );
                if (array_intersect($icols, $cols) === []) {
                    continue;
                }
                $name = 'gs_uq_' . $table . '_' . implode('_', $icols);
                try {
                    $this->pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS ' . $this->q($name) . ' ON ' . $this->q($table)
                        . ' (' . implode(', ', array_map([$this, 'q'], $icols)) . ')');
                } catch (Throwable $e) {
                    $this->warnings[] = "ایندکس یکتای {$table}(" . implode(',', $icols) . ') ساخته نشد: ' . $e->getMessage();
                }
            }
        }
    }

    // ───────────────────────── کمکی ─────────────────────────

    private function buildReference(string $baseline): ?PDO
    {
        try {
            $ref = new PDO('sqlite::memory:');
            $ref->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $ref->exec($baseline);

            return $ref;
        } catch (Throwable) {
            return null;
        }
    }

    /** @return list<string> */
    private function tables(PDO $pdo): array
    {
        return array_map('strval', $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' AND name <> 'schema_patches' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return list<string> */
    private function upgradeFiles(): array
    {
        $files = glob($this->schemaDir . '/upgrades/*.sql') ?: [];
        sort($files, SORT_STRING);

        return $files;
    }

    /** فقط ۱–۲ کوئری سبک؛ اگر ledger وجود نداشته باشد یا ناقص باشد false */
    private function isUpToDate(string $baselineFile): bool
    {
        try {
            $has = $this->pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='schema_patches'")->fetchColumn();
            if (! $has) {
                return false;
            }
            $key = 'baseline@' . substr((string) hash_file('sha256', $baselineFile), 0, 16);
            if (! $this->isRecorded($key)) {
                return false;
            }
            foreach ($this->upgradeFiles() as $f) {
                if (! $this->isRecorded(basename($f))) {
                    return false;
                }
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function ensureLedger(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS schema_patches (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            patch_code TEXT NOT NULL,
            script TEXT NOT NULL,
            checksum TEXT NOT NULL,
            applied_at TEXT NOT NULL,
            UNIQUE(patch_code, script)
        )');
    }

    private function isRecorded(string $script): bool
    {
        $q = $this->pdo->prepare('SELECT 1 FROM schema_patches WHERE patch_code = ? AND script = ?');
        $q->execute([self::LEDGER_CODE, $script]);

        return (bool) $q->fetchColumn();
    }

    private function record(string $script, string $checksum, string $note = ''): void
    {
        $this->pdo->prepare('INSERT OR IGNORE INTO schema_patches(patch_code, script, checksum, applied_at) VALUES (?,?,?,?)')
            ->execute([self::LEDGER_CODE, $script, $checksum, gmdate('c') . ($note !== '' ? " ({$note})" : '')]);
    }

    private function backup(): void
    {
        if ($this->backupDir === null) {
            return;
        }
        if (! is_dir($this->backupDir)) {
            @mkdir($this->backupDir, 0775, true);
        }
        $target = $this->backupDir . '/pre-upgrade-' . gmdate('Ymd-His') . '.sqlite';
        try {
            $this->pdo->exec('VACUUM INTO ' . $this->pdo->quote($target));
        } catch (Throwable $e) {
            $this->warnings[] = 'بکاپ پیش از upgrade گرفته نشد: ' . $e->getMessage();
        }
        $old = glob($this->backupDir . '/pre-upgrade-*.sqlite') ?: [];
        sort($old);
        foreach (array_slice($old, 0, max(0, count($old) - 3)) as $f) {
            @unlink($f);
        }
    }

    private function ifNotExists(string $sql): string
    {
        return (string) preg_replace('/^\s*CREATE\s+((?:UNIQUE\s+)?(?:TABLE|INDEX|TRIGGER|VIEW))\s+(?!IF\s+NOT\s+EXISTS)/i', 'CREATE $1 IF NOT EXISTS ', $sql, 1);
    }

    private function q(string $ident): string
    {
        return '"' . str_replace('"', '""', $ident) . '"';
    }

    /** @return array{status:string, fresh:bool, created_tables:list<string>, added_columns:array<string,list<string>>, upgrades:list<string>, warnings:list<string>, error:?string} */
    private function report(string $status, bool $fresh, ?string $error = null): array
    {
        return [
            'status'         => $status,
            'fresh'          => $fresh,
            'created_tables' => $this->createdTables,
            'added_columns'  => $this->addedColumns,
            'upgrades'       => $this->upgradesRun,
            'warnings'       => $this->warnings,
            'error'          => $error,
        ];
    }
}