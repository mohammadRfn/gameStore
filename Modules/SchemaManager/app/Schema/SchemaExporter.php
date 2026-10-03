<?php

declare(strict_types=1);

namespace Modules\SchemaManager\Schema;

use PDO;

/**
 * تولید baseline از دیتابیس توسعه و اثبات اینکه baseline همان اسکیما را بازتولید می‌کند.
 * (بدون وابستگی به Laravel؛ ورودی فقط یک PDO از دیتابیس SQLite توسعه است.)
 *
 * خروجی در outDir:
 *   baseline.sql        فقط ساختار (CREATE ... IF NOT EXISTS) - بدون هیچ داده‌ای از دیتابیس توسعه
 *   seed.sql            فقط ردیف‌های جدول‌هایی که صراحتاً در seedTables داده شوند
 *   baseline.meta.json  نسخه، زمان، فهرست جدول‌ها، upgrades شامل‌شده
 */
final class SchemaExporter
{
    /** @param list<string> $exclude جدول‌هایی که خود برنامه می‌سازد و نباید در baseline باشند */
    public function __construct(
        private readonly PDO $dev,
        private readonly array $exclude = ['schema_patches', 'license_patches'],
    ) {}

    /**
     * @param list<string> $seedTables
     * @return array{tables:list<string>, objects:int, seed_rows:int, upgrades:list<string>, outDir:string}
     */
    public function export(string $outDir, array $seedTables = [], string $version = ''): array
    {
        if (! is_dir($outDir) && ! @mkdir($outDir, 0775, true) && ! is_dir($outDir)) {
            throw new \RuntimeException("ساخت پوشه ممکن نشد: {$outDir}");
        }
        $this->dev->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $objects = $this->objects($this->dev);
        $tables  = [];
        $sql     = "-- baseline اسکیما (تولیدشده توسط schema:export) — دستی ویرایش نکنید\n"
                 . '-- ' . gmdate('c') . ($version !== '' ? " | version {$version}" : '') . "\n\n";

        foreach ($objects as $o) {
            if ($o['type'] === 'table') {
                $tables[] = (string) $o['name'];
            }
            $sql .= $this->ifNotExists(rtrim((string) $o['sql'], "; \t\n\r")) . ";\n\n";
        }
        file_put_contents($outDir . '/baseline.sql', $sql);

        $seedRows = 0;
        $seedSql  = '';
        foreach ($seedTables as $t) {
            if (! in_array($t, $tables, true)) {
                throw new \RuntimeException("جدول seed «{$t}» در دیتابیس وجود ندارد یا جزو baseline نیست.");
            }
            [$stmts, $n] = $this->dumpRows($t);
            $seedSql    .= $stmts;
            $seedRows   += $n;
        }
        if ($seedSql !== '') {
            file_put_contents($outDir . '/seed.sql', "-- داده‌ی اولیه؛ فقط روی نصب تازه اجرا می‌شود\n" . $seedSql);
        } else {
            @unlink($outDir . '/seed.sql');
        }

        $upgrades = array_map('basename', glob($outDir . '/upgrades/*.sql') ?: []);
        sort($upgrades);

        file_put_contents($outDir . '/baseline.meta.json', json_encode([
            'version'          => $version,
            'generated_at'     => gmdate('c'),
            'tables'           => $tables,
            'seed_tables'      => $seedTables,
            'includes_upgrades' => $upgrades,
            'baseline_sha256'  => hash_file('sha256', $outDir . '/baseline.sql'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return ['tables' => $tables, 'objects' => count($objects), 'seed_rows' => $seedRows, 'upgrades' => $upgrades, 'outDir' => $outDir];
    }

    /**
     * یک دیتابیس تازه از روی baseline (+seed) می‌سازد و با دیتابیس توسعه مقایسه می‌کند.
     *
     * @return list<string> مشکلات؛ خالی = baseline دقیقاً همان اسکیمای توسعه را بازتولید می‌کند
     */
    public function verify(string $schemaDir): array
    {
        $problems = [];
        $baseline = $schemaDir . '/baseline.sql';
        if (! is_file($baseline)) {
            return ['baseline.sql وجود ندارد؛ ابتدا schema:export را اجرا کنید.'];
        }

        $fresh = new PDO('sqlite::memory:');
        $fresh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        try {
            $fresh->exec((string) file_get_contents($baseline));
            if (is_file($schemaDir . '/seed.sql')) {
                $fresh->exec((string) file_get_contents($schemaDir . '/seed.sql'));
            }
        } catch (\Throwable $e) {
            return ['اجرای baseline/seed روی دیتابیس تازه شکست خورد: ' . $e->getMessage()];
        }

        $devObjs   = $this->index($this->objects($this->dev));
        $freshObjs = $this->index($this->objects($fresh));

        foreach ($devObjs as $key => $o) {
            if (! isset($freshObjs[$key])) {
                $problems[] = "در baseline نیست: {$key}";
                continue;
            }
            if ($o['type'] === 'table') {
                $a = $this->columns($this->dev, (string) $o['name']);
                $b = $this->columns($fresh, (string) $o['name']);
                foreach ($a as $col => $def) {
                    if (! isset($b[$col])) {
                        $problems[] = "ستون {$o['name']}.{$col} در baseline نیست";
                    } elseif ($b[$col] !== $def) {
                        $problems[] = "تعریف ستون {$o['name']}.{$col} فرق دارد: dev={$def} baseline={$b[$col]}";
                    }
                }
                foreach (array_diff_key($b, $a) as $col => $_) {
                    $problems[] = "ستون اضافه در baseline: {$o['name']}.{$col}";
                }
            }
        }
        foreach (array_diff_key($freshObjs, $devObjs) as $key => $_) {
            $problems[] = "در baseline هست ولی در دیتابیس توسعه نیست: {$key}";
        }

        return $problems;
    }

    // ───────────────────────── کمکی ─────────────────────────

    /** @return list<array{type:string,name:string,tbl_name:string,sql:string}> مرتب: table → index → trigger → view */
    private function objects(PDO $pdo): array
    {
        $rows = $pdo->query(
            "SELECT type, name, tbl_name, sql FROM sqlite_master
             WHERE sql IS NOT NULL AND name NOT LIKE 'sqlite_%'
             ORDER BY CASE type WHEN 'table' THEN 0 WHEN 'index' THEN 1 WHEN 'trigger' THEN 2 ELSE 3 END, name",
        )->fetchAll(PDO::FETCH_ASSOC);

        return array_values(array_filter($rows, fn (array $r): bool => ! in_array($r['tbl_name'], $this->exclude, true) && ! in_array($r['name'], $this->exclude, true)));
    }

    /** @param list<array<string,mixed>> $objs @return array<string,array<string,mixed>> */
    private function index(array $objs): array
    {
        $out = [];
        foreach ($objs as $o) {
            $out[$o['type'] . ':' . $o['name']] = $o;
        }

        return $out;
    }

    /** @return array<string,string> نام ستون → امضای تعریف */
    private function columns(PDO $pdo, string $table): array
    {
        $out = [];
        foreach ($pdo->query('PRAGMA table_info("' . str_replace('"', '""', $table) . '")')->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $out[strtolower((string) $c['name'])] = strtolower(trim((string) $c['type'])) . '|nn' . $c['notnull'] . '|d' . ($c['dflt_value'] ?? 'NULL') . '|pk' . $c['pk'];
        }

        return $out;
    }

    /** @return array{0:string,1:int} */
    private function dumpRows(string $table): array
    {
        $q    = '"' . str_replace('"', '""', $table) . '"';
        $cols = array_map(static fn ($c) => (string) $c['name'], $this->dev->query("PRAGMA table_info({$q})")->fetchAll(PDO::FETCH_ASSOC));
        if ($cols === []) {
            return ['', 0];
        }
        $colList = implode(', ', array_map(static fn ($c) => '"' . str_replace('"', '""', $c) . '"', $cols));
        $quoted  = implode(', ', array_map(static fn ($c) => 'quote("' . str_replace('"', '""', $c) . '")', $cols));

        $out = '';
        $n   = 0;
        foreach ($this->dev->query("SELECT {$quoted} FROM {$q}")->fetchAll(PDO::FETCH_NUM) as $row) {
            $out .= "INSERT OR IGNORE INTO {$q} ({$colList}) VALUES (" . implode(', ', $row) . ");\n";
            $n++;
        }

        return [$out, $n];
    }

    private function ifNotExists(string $sql): string
    {
        return (string) preg_replace('/^\s*CREATE\s+((?:UNIQUE\s+)?(?:TABLE|INDEX|TRIGGER|VIEW))\s+(?!IF\s+NOT\s+EXISTS)/i', 'CREATE $1 IF NOT EXISTS ', $sql, 1);
    }
}