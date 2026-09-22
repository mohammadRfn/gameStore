<?php

declare(strict_types=1);

namespace Modules\AuditLog\Listeners;

use Illuminate\Events\Dispatcher;
use Modules\AuditLog\Enums\AuditAction;
use Modules\AuditLog\Enums\LogLevel;
use Modules\AuditLog\Services\AuditLogger;

/**
 * اتصال به رویدادهای اختصاصی ماژول‌های گیم‌استور.
 * ثبت این listenerها مشروط به وجود کلاس رویداد است، بنابراین اگر
 * ماژولی غیرفعال یا حذف شود چیزی نمی‌شکند.
 */
class SystemEventSubscriber
{
    /** رویدادهای شناخته‌شده‌ی پروژه (کلاس => [action, توضیح]) */
    public const KNOWN_EVENTS = [
        '\\Modules\\Setting\\Events\\SettingsChanged' => [AuditAction::SettingChanged, 'تنظیمات سیستم تغییر کرد'],
    ];

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function handleSettingsChanged(object $event): void
    {
        $this->audit->system(AuditAction::SettingChanged, 'تنظیمات سیستم تغییر کرد', [
            'event'   => $event::class,
            'payload' => $this->payload($event),
        ], LogLevel::Notice);
    }

    /**
     * fallback عمومی برای هر رویداد ثبت‌شده‌ی دیگر.
     */
    public function handleGenericSystemEvent(string $eventName, array $payload): void
    {
        $this->audit->system('system.event', 'رویداد سیستمی: ' . class_basename($eventName), [
            'event'   => $eventName,
            'payload' => $this->payload($payload[0] ?? null),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(mixed $event): array
    {
        if (! is_object($event)) {
            return [];
        }

        $data = get_object_vars($event);

        return $data === [] ? ['class' => $event::class] : $data;
    }

    public function subscribe(Dispatcher $events): void
    {
        foreach (self::KNOWN_EVENTS as $class => $meta) {
            if (class_exists($class)) {
                $events->listen($class, [self::class, 'handleSettingsChanged']);
            }
        }
    }
}
