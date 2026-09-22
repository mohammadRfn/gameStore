<?php

declare(strict_types=1);

namespace Modules\AuditLog\Support\Concerns;

use Modules\AuditLog\Models\AuditLog;
use Modules\AuditLog\Observers\AuditableObserver;

/**
 * افزودن این trait به هر مدل، آن را به‌صورت خودکار رصد می‌کند.
 * (راه دوم و بدون تغییر کد مدل: اضافه‌کردن کلاس مدل به
 *  config('auditlog.capture.model.observe'))
 *
 * سفارشی‌سازی اختیاری در مدل:
 *   protected array $auditExclude = ['internal_notes'];
 *   protected array $auditOnly    = ['status', 'total_amount'];
 *   public function auditLabel(): ?string { return $this->invoice_number; }
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::observe(AuditableObserver::class);
    }

    /** @return list<string> */
    public function auditExcludedAttributes(): array
    {
        return array_merge(
            (array) config('auditlog.capture.model.ignore_attributes', []),
            property_exists($this, 'auditExclude') ? (array) $this->auditExclude : [],
            $this->getHidden(),
        );
    }

    /** @return list<string> */
    public function auditOnlyAttributes(): array
    {
        return property_exists($this, 'auditOnly') ? (array) $this->auditOnly : [];
    }

    public function auditLabel(): ?string
    {
        foreach ((array) config('auditlog.capture.model.label_attributes', []) as $attribute) {
            $value = $this->getAttribute($attribute);

            if (is_scalar($value) && (string) $value !== '') {
                return mb_substr((string) $value, 0, 191);
            }
        }

        return null;
    }

    /** لاگ‌های ثبت‌شده برای این رکورد. */
    public function auditLogs()
    {
        return AuditLog::query()
            ->where('entity_type', static::class)
            ->where('entity_id', $this->getKey())
            ->orderByDesc('id');
    }
}
