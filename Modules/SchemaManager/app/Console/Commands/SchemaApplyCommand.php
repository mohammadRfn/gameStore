<?php

declare(strict_types=1);

namespace Modules\SchemaManager\Console\Commands;

use Illuminate\Console\Command;
use Modules\SchemaManager\Services\SchemaRunner;

class SchemaApplyCommand extends Command
{
    protected $signature = 'schema:apply';

    protected $description = 'اجرای دستی ساخت/هم‌سطح‌سازی اسکیما از database/schema (همان کاری که برنامه خودکار می‌کند)';

    public function handle(SchemaRunner $schema): int
    {
        $r = $schema->ensure(force: true);
        $this->line('status: ' . $r['status'] . (isset($r['reason']) ? " ({$r['reason']})" : ''));
        if ($r['created_tables']) {
            $this->line('جدول‌های ساخته‌شده: ' . implode('، ', $r['created_tables']));
        }
        foreach ($r['added_columns'] as $t => $cols) {
            $this->line("ستون‌های اضافه‌شده به {$t}: " . implode('، ', $cols));
        }
        if ($r['upgrades']) {
            $this->line('upgrades: ' . implode('، ', $r['upgrades']));
        }
        foreach ($r['warnings'] as $w) {
            $this->warn($w);
        }
        if ($r['error']) {
            $this->error($r['error']);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}