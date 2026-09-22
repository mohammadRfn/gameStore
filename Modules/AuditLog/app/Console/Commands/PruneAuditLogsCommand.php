<?php

declare(strict_types=1);

namespace Modules\AuditLog\Console\Commands;

use Illuminate\Console\Command;
use Modules\AuditLog\Services\AuditLogPruner;

/**
 * php artisan auditlog:prune --days=90 --dry-run
 */
class PruneAuditLogsCommand extends Command
{
    protected $signature = 'auditlog:prune
                            {--days= : تعداد روز نگه‌داری (پیش‌فرض از کانفیگ)}
                            {--dry-run : فقط گزارش بده، حذف نکن}
                            {--force : بدون پرسش تأیید}';

    protected $description = 'پاک‌سازی لاگ‌های قدیمی گیم‌استور با رعایت سیاست نگه‌داری';

    public function handle(AuditLogPruner $pruner): int
    {
        $days = $this->option('days') !== null ? (int) $this->option('days') : null;
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! $this->option('force') && ! $this->confirm('حذف قطعی لاگ‌های قدیمی انجام شود؟', false)) {
            $this->warn('لغو شد.');

            return self::SUCCESS;
        }

        $result = $pruner->prune($days, $dryRun);

        $this->info(sprintf(
            '%s %d رکورد لاگ و %d دسته.',
            $dryRun ? 'قابل حذف:' : 'حذف شد:',
            $result['deleted'],
            $result['batches'],
        ));

        return self::SUCCESS;
    }
}
