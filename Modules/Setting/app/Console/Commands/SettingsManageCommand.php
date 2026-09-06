<?php

declare(strict_types=1);

namespace Modules\Setting\App\Console\Commands;

use Modules\App\Enums\Settings\SettingGroup;
use Modules\App\Services\Setting\SettingService;
use Illuminate\Console\Command;

/**
 * Command مدیریت تنظیمات از طریق CLI.
 *
 * مثال‌ها:
 *   php artisan settings:list
 *   php artisan settings:list --group=general
 *   php artisan settings:get general.calendar
 *   php artisan settings:set general.calendar jalali
 *   php artisan settings:reset general.calendar
 *   php artisan settings:reset-group general
 *   php artisan settings:reset-all
 *   php artisan settings:flush-cache
 *   php artisan settings:export ./settings.json
 *   php artisan settings:import ./settings.json
 */
class SettingsManageCommand extends Command
{
    protected $signature = 'settings:manage
        {action=list : Action to perform (list|get|set|reset|reset-group|reset-all|flush-cache|export|import)}
        {key? : Setting key (for get/set/reset)}
        {value? : New value (for set)}
        {--group= : Filter by group (for list)}
        {--file= : File path (for export/import)}
        {--force : Skip confirmation for destructive actions}';

    protected $description = 'مدیریت تنظیمات مرکزی اپلیکیشن';

    public function handle(SettingService $settings): int
    {
        $action = (string) $this->argument('action');

        return match ($action) {
            'list' => $this->handleList($settings),
            'get' => $this->handleGet($settings),
            'set' => $this->handleSet($settings),
            'reset' => $this->handleReset($settings),
            'reset-group' => $this->handleResetGroup($settings),
            'reset-all' => $this->handleResetAll($settings),
            'flush-cache' => $this->handleFlushCache($settings),
            'export' => $this->handleExport($settings),
            'import' => $this->handleImport($settings),
            default => $this->handleUnknown($action),
        };
    }

    private function handleList(SettingService $settings): int
    {
        $groupFilter = $this->option('group');

        $groups = $groupFilter
            ? [SettingGroup::from($groupFilter)]
            : SettingGroup::cases();

        foreach ($groups as $group) {
            $this->newLine();
            $this->info("🔹 {$group->label()} [{$group->value}]");

            $rows = [];
            foreach ($settings->metaForGroup($group) as $key => $meta) {
                $value = $settings->get($key);
                if ($value instanceof \BackedEnum) {
                    $value = $value->value;
                }
                $rows[] = [
                    $key,
                    $meta['type'],
                    is_scalar($value) ? (string) $value : gettype($value),
                    $meta['label'] ?? '-',
                ];
            }

            $this->table(['Key', 'Type', 'Value', 'Label'], $rows);
        }

        return self::SUCCESS;
    }

    private function handleGet(SettingService $settings): int
    {
        $key = (string) $this->argument('key');
        if (empty($key)) {
            $this->error('Key is required.');
            return self::FAILURE;
        }

        $value = $settings->get($key);
        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        }

        $this->info("{$key} = " . (is_scalar($value) ? (string) $value : json_encode($value)));
        return self::SUCCESS;
    }

    private function handleSet(SettingService $settings): int
    {
        $key = (string) $this->argument('key');
        $value = $this->argument('value');

        if (empty($key)) {
            $this->error('Key is required.');
            return self::FAILURE;
        }

        try {
            $settings->set($key, $value);
            $this->info("✓ Setting [{$key}] updated.");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function handleReset(SettingService $settings): int
    {
        $key = (string) $this->argument('key');
        if (empty($key)) {
            $this->error('Key is required.');
            return self::FAILURE;
        }

        $settings->reset($key);
        $this->info("✓ Setting [{$key}] reset to default.");
        return self::SUCCESS;
    }

    private function handleResetGroup(SettingService $settings): int
    {
        $group = $this->option('group');
        if (empty($group)) {
            $this->error('--group is required.');
            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Reset group [{$group}] to defaults?")) {
            return self::SUCCESS;
        }

        $settings->resetGroup(SettingGroup::from($group));
        $this->info("✓ Group [{$group}] reset to defaults.");
        return self::SUCCESS;
    }

    private function handleResetAll(SettingService $settings): int
    {
        if (! $this->option('force') && ! $this->confirm('Reset ALL settings to defaults? This cannot be undone.')) {
            return self::SUCCESS;
        }

        $settings->resetAll();
        $this->info('✓ All settings reset to defaults.');
        return self::SUCCESS;
    }

    private function handleFlushCache(SettingService $settings): int
    {
        $settings->flushCache();
        $this->info('✓ Settings cache flushed.');
        return self::SUCCESS;
    }

    private function handleExport(SettingService $settings): int
    {
        $file = $this->option('file') ?: 'settings-export-' . now()->format('Ymd_His') . '.json';
        $data = $settings->export();
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        file_put_contents($file, $json);
        $this->info("✓ Exported to [{$file}] (" . count($data) . ' settings).');
        return self::SUCCESS;
    }

    private function handleImport(SettingService $settings): int
    {
        $file = $this->option('file');
        if (empty($file) || ! file_exists($file)) {
            $this->error('File not found: ' . $file);
            return self::FAILURE;
        }

        $content = file_get_contents($file);
        $data = json_decode($content, true);
        if (! is_array($data)) {
            $this->error('Invalid JSON file.');
            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Import ' . count($data) . ' settings?')) {
            return self::SUCCESS;
        }

        $settings->import($data);
        $this->info('✓ Settings imported successfully.');
        return self::SUCCESS;
    }

    private function handleUnknown(string $action): int
    {
        $this->error("Unknown action [{$action}].");
        return self::FAILURE;
    }
}
