<?php

namespace App\Console\Commands;

use App\Models\BackupRun;
use App\Services\Backup\BackupService;
use Illuminate\Console\Command;
use Throwable;

/**
 * اجرای بکاپ از خط فرمان (برای زمان‌بندی خودکار در اپ دسکتاپ).
 *
 *   php artisan backup:run
 *   php artisan backup:run --path="D:\Backups" --mode=database --label=nightly
 *   php artisan backup:run --import --path="D:\Backups\GameStore_export_..." --strategy=merge --dry-run
 *
 * زمان‌بندی روزانه در routes/console.php :
 *   Schedule::command('backup:run --label=auto')->dailyAt('23:30');
 */
class BackupRunCommand extends Command
{
    protected $signature = 'backup:run
        {--path=            : دایرکتوری مقصد/مبدا روی سیستم}
        {--mode=full        : full|database|media}
        {--strategy=merge   : merge|replace|skip_existing|fail_on_conflict (فقط ایمپورت)}
        {--label=           : برچسب دلخواه}
        {--entities=        : لیست موجودیت‌ها با کاما}
        {--import           : به‌جای خروجی، ایمپورت انجام شود}
        {--dry-run          : اجرای آزمایشی بدون ذخیره}
        {--no-media         : بدون تصاویر}';

    protected $description = 'گرفتن پشتیبان یا بازیابی اطلاعات فروشگاه (CSV + تصاویر)';

    public function handle(BackupService $service): int
    {
        $entities = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('entities')))));

        $options = array_filter([
            'mode'          => $this->option('mode'),
            'entities'      => $entities ?: null,
            'label'         => $this->option('label') ?: 'cli',
            'dry_run'       => (bool) $this->option('dry-run'),
            'include_media' => ! $this->option('no-media'),
            'is_auto'       => true,
        ], fn ($v) => $v !== null);

        try {
            if ($this->option('import')) {
                $run = $service->import($options + [
                    'source_path' => $this->option('path') ?: null,
                    'strategy'    => $this->option('strategy'),
                ]);
            } else {
                $run = $service->export($options + [
                    'destination_path' => $this->option('path') ?: null,
                ]);
            }
        } catch (Throwable $e) {
            $this->error('خطا: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['شناسه', 'نوع', 'وضعیت', 'رکورد', 'فایل', 'حجم (MB)', 'مسیر'],
            [[
                $run->id,
                $run->direction,
                $run->status,
                $run->total_rows,
                $run->total_files,
                round($run->total_bytes / 1048576, 2),
                $run->run_path,
            ]],
        );

        return $run->status === BackupRun::STATUS_FAILED ? self::FAILURE : self::SUCCESS;
    }
}
