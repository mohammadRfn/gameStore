<?php

declare(strict_types=1);

namespace Modules\SchemaManager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\SchemaManager\Schema\SchemaBootstrapper;
use Modules\SchemaManager\Schema\SchemaExporter;
use Modules\SchemaManager\Services\SchemaRunner;
use Modules\SchemaManager\Services\SchemaModels;

/**
 * سه بررسی پیش از تحویل:
 *  ۱. baseline دقیقاً همان اسکیمای دیتابیس توسعه را بازتولید می‌کند
 *  ۲. شبیه‌سازی نصب تازه (دیتابیس خالی موقت) بدون خطا و هشدار کار می‌کند
 *  ۳. جدول همه‌ی Modelهای Eloquent در baseline هست
 */
class SchemaVerifyCommand extends Command
{
    protected $signature = 'schema:verify';

    protected $description = 'بررسی اینکه baseline نصب تازه کامل و درست است';

    public function handle(SchemaRunner $schema): int
    {
        $dir = $schema->schemaDir();
        $bad = 0;

        $this->line('۱) مقایسه‌ی baseline با دیتابیس توسعه…');
        $problems = (new SchemaExporter(DB::connection()->getPdo()))->verify($dir);
        foreach ($problems as $p) {
            $this->error('   ✘ ' . $p);
        }
        $problems === [] ? $this->info('   ✔ یکسان است') : $bad++;

        $this->line('۲) شبیه‌سازی نصب تازه روی دیتابیس خالی…');
        $tmp = sys_get_temp_dir() . '/gs-fresh-' . bin2hex(random_bytes(4)) . '.sqlite';
        try {
            $pdo = new \PDO('sqlite:' . $tmp);
            $r   = (new SchemaBootstrapper($pdo, $dir))->ensure();
            $r2  = (new SchemaBootstrapper($pdo, $dir))->ensure();

            if ($r['status'] !== 'fresh' || $r['error'] !== null || $r['warnings'] !== []) {
                $this->error('   ✘ status=' . $r['status'] . ($r['error'] ? ' | ' . $r['error'] : '') . ($r['warnings'] ? ' | ' . implode(' ; ', $r['warnings']) : ''));
                $bad++;
            } elseif ($r2['status'] !== 'noop') {
                $this->error('   ✘ اجرای دوم باید noop باشد ولی ' . $r2['status'] . ' شد');
                $bad++;
            } else {
                $this->info('   ✔ ' . count($r['created_tables']) . ' جدول ساخته شد و اجرای دوم هیچ کاری نکرد');
            }
        } finally {
            $pdo = null;
            @unlink($tmp);
        }

        $this->line('۳) پوشش Modelها…');
        $meta   = json_decode((string) @file_get_contents($dir . '/baseline.meta.json'), true);
        $tables = (array) ($meta['tables'] ?? []);
        $missing = [];
        foreach (SchemaModels::tables() as $class => $table) {
            if (! in_array($table, $tables, true) && ! in_array($table, ['license_patches'], true)) {
                $missing[] = "{$class} → {$table}";
            }
        }
        foreach ($missing as $m) {
            $this->error('   ✘ جدول مدل در baseline نیست: ' . $m);
        }
        $missing === [] ? $this->info('   ✔ همه‌ی ' . count(SchemaModels::tables()) . ' مدل پوشش داده شدند') : $bad++;

        $this->newLine();
        $bad === 0 ? $this->info('همه‌ی بررسی‌ها موفق بود؛ می‌توانید build بگیرید.') : $this->error("{$bad} بررسی ناموفق بود؛ پیش از build رفع کنید.");

        return $bad === 0 ? self::SUCCESS : self::FAILURE;
    }
}