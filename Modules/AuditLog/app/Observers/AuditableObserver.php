<?php

declare(strict_types=1);

namespace Modules\AuditLog\Observers;

use Illuminate\Database\Eloquent\Model;
use Modules\AuditLog\Enums\AuditAction;
use Modules\AuditLog\Services\AuditLogger;

/**
 * Observer عمومی برای همه‌ی مدل‌های پروژه.
 * بدون نیاز به تغییر کد ماژول‌های دیگر، از طریق
 * config('auditlog.capture.model.observe') ثبت می‌شود.
 */
class AuditableObserver
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function created(Model $model): void
    {
        if (! $this->shouldLog('created')) {
            return;
        }

        $this->audit->model(AuditAction::Created, $model, null, $this->attributes($model, $model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        if (! $this->shouldLog('updated')) {
            return;
        }

        $changes = $this->attributes($model, $model->getChanges());

        if ($changes === []) {
            return; // فقط ستون‌های نادیده‌گرفته‌شده تغییر کرده‌اند
        }

        $old = [];

        foreach (array_keys($changes) as $key) {
            $old[$key] = $model->getOriginal($key);
        }

        $this->audit->model(AuditAction::Updated, $model, $old, $changes);
    }

    public function deleted(Model $model): void
    {
        if (! $this->shouldLog('deleted')) {
            return;
        }

        $softDeleted = method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting();

        $this->audit->model(
            $softDeleted ? AuditAction::Deleted : AuditAction::ForceDeleted,
            $model,
            $this->attributes($model, $model->getOriginal()),
            null,
        );
    }

    public function restored(Model $model): void
    {
        if (! $this->shouldLog('restored')) {
            return;
        }

        $this->audit->model(AuditAction::Restored, $model, null, $this->attributes($model, $model->getAttributes()));
    }

    public function forceDeleted(Model $model): void
    {
        if (! $this->shouldLog('forceDeleted')) {
            return;
        }

        $this->audit->model(AuditAction::ForceDeleted, $model, $this->attributes($model, $model->getOriginal()), null);
    }

    /**
     * فیلتر ستون‌های حساس/بی‌اهمیت.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function attributes(Model $model, array $attributes): array
    {
        $excluded = method_exists($model, 'auditExcludedAttributes')
            ? $model->auditExcludedAttributes()
            : array_merge((array) config('auditlog.capture.model.ignore_attributes', []), $model->getHidden());

        $only = method_exists($model, 'auditOnlyAttributes') ? $model->auditOnlyAttributes() : [];

        $filtered = [];

        foreach ($attributes as $key => $value) {
            if (in_array($key, $excluded, true)) {
                continue;
            }

            if ($only !== [] && ! in_array($key, $only, true)) {
                continue;
            }

            $filtered[$key] = $value;
        }

        return $filtered;
    }

    private function shouldLog(string $event): bool
    {
        return (bool) config('auditlog.capture.model.enabled', true)
            && in_array($event, (array) config('auditlog.capture.model.events', []), true);
    }
}
