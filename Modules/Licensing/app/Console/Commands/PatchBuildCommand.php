<?php

declare(strict_types=1);

namespace Modules\Licensing\Console\Commands;

use Illuminate\Console\Command;
use Modules\Licensing\Patching\Builder\PatchBuilder;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * ساخت بسته‌ی پچ از خود پروژه (فقط سمت توسعه‌دهنده).
 *
 *   php artisan licensing:patch-build init    --from="<پوشه‌ی app نصب‌شده>" --ver=1.0.0   (یک‌بار)
 *   php artisan licensing:patch-build make    --title="عنوان" [--build]                   (هر پچ)
 *   php artisan licensing:patch-build release                                             (بعد از انتشار)
 */
class PatchBuildCommand extends Command
{
    protected $signature = 'licensing:patch-build
        {action : init | make | release}
        {--from= : (init) ریشه‌ی app نسخه‌ی منتشرشده؛ پیش‌فرض: همین پروژه}
        {--ver= : (init) نسخه‌ی مبنا، مثل 1.0.0}
        {--title= : (make) عنوان پچ}
        {--desc= : (make) توضیح}
        {--to= : (make) نسخه‌ی مقصد؛ پیش‌فرض: یک پچ بالاتر از مبنا}
        {--from-max= : (make) حداکثر نسخه‌ی قابل‌ارتقا؛ پیش‌فرض: نسخه‌ی مبنا}
        {--id= : (make) شناسه‌ی پچ؛ پیش‌فرض p-<نسخه>}
        {--restart=1 : (make) 1 = نیاز به ری‌استارت}
        {--sql= : (make) پوشه‌ی فایل‌های .sql که با پچ روی دیتابیس مشتری اجرا می‌شوند (به ترتیب نام)}
        {--work= : پوشه‌ی کاری؛ پیش‌فرض: کنار پروژه، پوشه‌ی patch-work}
        {--build : (make) قبل از ساخت، npm run build را اجرا کن}';

    protected $description = 'ساخت بسته‌ی پچ (zip) از تفاوت پروژه با نسخه‌ی منتشرشده؛ فقط در محیط توسعه';

    public function handle(): int
    {
        // روی برنامه‌ی نصب‌شده‌ی مشتری هیچ کاری نمی‌کند
        if (env('NATIVEPHP_RUNNING', false) === true) {
            $this->error('این دستور فقط در محیط توسعه قابل اجراست.');

            return self::FAILURE;
        }

        $work = (string) ($this->option('work') ?: env('PATCH_WORK_DIR') ?: dirname(base_path()) . DIRECTORY_SEPARATOR . 'patch-work');
        $builder = new PatchBuilder($work);

        try {
            return match ($this->argument('action')) {
                'init' => $this->doInit($builder),
                'make' => $this->doMake($builder),
                'release' => $this->doRelease($builder),
                default => $this->bail('action باید init، make یا release باشد.'),
            };
        } catch (RuntimeException $e) {
            return $this->bail($e->getMessage());
        }
    }

    private function doInit(PatchBuilder $b): int
    {
        $ver = (string) $this->option('ver');
        if ($ver === '') {
            return $this->bail('--ver الزامی است (مثلاً --ver=1.0.0).');
        }
        $n = $b->init((string) ($this->option('from') ?: base_path()), $ver);
        $this->info("مبنا ثبت شد: $n فایل، نسخه‌ی $ver");
        $this->line('  مسیر: ' . $b->workDir());

        return self::SUCCESS;
    }

    private function doMake(PatchBuilder $b): int
    {
        if ($this->option('build')) {
            $this->line('npm run build ...');
            $p = Process::fromShellCommandline('npm run build', base_path(), null, null, 900);
            $p->run(fn (string $type, string $buf) => $this->output->write($buf));
            if (! $p->isSuccessful()) {
                return $this->bail('npm run build ناموفق بود.');
            }
        }

        $r = $b->make(base_path(), (string) $this->option('title'), array_filter([
            'desc' => (string) $this->option('desc'),
            'to' => $this->option('to') ?: null,
            'from_max' => $this->option('from-max') ?: null,
            'id' => $this->option('id') ?: null,
            'sql' => $this->option('sql') ?: null,
            'restart' => (string) ($this->option('restart') ?? '1') === '1',
        ], static fn ($v) => $v !== null));

        $this->info(sprintf('ساخته شد: %s (%s KB)', $r['zip'], number_format($r['size'] / 1024, 1)));
        $this->line(sprintf('  add: %d | replace: %d | delete: %d', $r['stat']['add'], $r['stat']['replace'], $r['stat']['delete']));
        $this->line(sprintf(
            '  نسخه: %s%s → %s | requires_restart: %s',
            $r['from'],
            $r['from_max'] !== $r['from'] ? '..' . $r['from_max'] : '',
            $r['to'],
            $r['restart'] ? 'yes' : 'no'
        ));
        if ($r['sql'] !== []) {
            $this->line('  اسکریپت SQL (' . count($r['sql']) . '): ' . implode('، ', $r['sql']));
        }
        $this->line('  تغییرهای غیرِ بیلد (' . count($r['code']) . '):');
        foreach (array_slice($r['code'], 0, 25) as $e) {
            $this->line("    [{$e['action']}] {$e['path']}");
        }
        if (count($r['code']) > 25) {
            $this->line('    ... و ' . (count($r['code']) - 25) . ' فایل دیگر');
        }
        foreach ($r['warnings'] as $w) {
            $this->warn("  ⚠ $w");
        }
        $this->line('  sha256 بسته: ' . $r['sha256']);
        $this->line('بعد از انتشار موفق: php artisan licensing:patch-build release');

        return self::SUCCESS;
    }

    private function doRelease(PatchBuilder $b): int
    {
        $r = $b->release(base_path());
        $this->info("مبنا به‌روز شد ({$r['files']} فایل). نسخه‌ی فعلی: {$r['version']}");

        return self::SUCCESS;
    }

    private function bail(string $msg): int
    {
        $this->error($msg);

        return self::FAILURE;
    }
}