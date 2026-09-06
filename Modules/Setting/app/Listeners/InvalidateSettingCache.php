<?php

declare(strict_types=1);

namespace Modules\Setting\Listeners;

use Modules\Setting\Events\SettingsChanged;
use Modules\Setting\Services\Setting\Contracts\SettingRepositoryContract;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Log;

/**
 * لیسنری که با انتشار SettingsChanged، کش تنظیمات را باطل می‌کند.
 * همچنین تغییرات حساس (مثل مسیر دیتابیس) را لاگ می‌کند.
 */
class InvalidateSettingCache
{
    public function __construct(
        private readonly CacheRepository $cache,
        private readonly SettingRepositoryContract $repository,
    ) {
    }

    public function handle(SettingsChanged $event): void
    {
        // 1) invalidate کش اصلی
        $cacheKey = (string) config('setting.cache.key', 'app.settings.v1');
        $this->cache->forget($cacheKey);

        // 2) invalidate کش اختصاصی هر group
        foreach ($event->newValues as $key => $_) {
            $parts = explode('.', $key, 2);
            if (count($parts) === 2) {
                $this->cache->forget("{$cacheKey}.group.{$parts[0]}");
            }
        }

        // 3) لاگ تغییرات حساس (audit trail ساده)
        $sensitiveKeys = ['desktop.database_path', 'desktop.auto_update_url'];
        foreach ($event->changedKeys() as $key) {
            if (in_array($key, $sensitiveKeys, true)) {
                Log::channel('settings')->info('Setting changed: ' . $key, [
                    'from' => $event->oldValues[$key] ?? null,
                    'to'   => $event->newValues[$key],
                    'user' => $event->userId,
                ]);
            }
        }

        // 4) در صورت تغییر مسیر دیتابیس، نیاز به restart اپ دسکتاپ داریم.
        //    در این حالت یک flag در cache می‌گذاریم که UI آن را نشان دهد.
        if (in_array('desktop.database_path', $event->changedKeys(), true)) {
            $this->cache->put('desktop.restart_required', true, now()->addHours(2));
        }
    }
}
