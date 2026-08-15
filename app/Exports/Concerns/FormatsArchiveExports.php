<?php

namespace App\Exports\Concerns;

use App\Models\ArchivedRecord;
use Illuminate\Support\Carbon;
use Morilog\Jalali\Jalalian;

/**
 * منطق مشترک بین همه‌ی کلاس‌های اکسپورت بایگانی: فرمت تاریخ جلالی
 * (هماهنگ با morilog/jalali که در باقی پروژه هم استفاده می‌شود)،
 * فرمت مبلغ، برچسب فارسی وضعیت‌ها و دسترسی امن به snapshot_json.
 */
trait FormatsArchiveExports
{
    protected function jalali(mixed $date): string
    {
        if (!$date) {
            return '-';
        }

        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);

        return class_exists(Jalalian::class)
            ? Jalalian::fromCarbon($carbon)->format('Y/m/d H:i')
            : $carbon->format('Y-m-d H:i');
    }

    protected function amount(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return number_format((float) $value, 0);
    }

    protected function archiveStatusLabel(ArchivedRecord $record): string
    {
        return match ($record->archive_status) {
            ArchivedRecord::STATUS_TRANSFERRED => 'منتقل‌شده (حذف از بخش اصلی)',
            ArchivedRecord::STATUS_COPIED       => 'کپی‌شده (هنوز در بخش اصلی فعال است)',
            default                              => (string) $record->archive_status,
        };
    }

    protected function paymentStatusLabel(?string $status): string
    {
        return match ($status) {
            'paid'     => 'پرداخت‌شده',
            'unpaid'   => 'پرداخت‌نشده',
            'returned' => 'مرجوع‌شده',
            default    => (string) $status,
        };
    }

    protected function snapshot(ArchivedRecord $record): array
    {
        return is_array($record->snapshot_json) ? $record->snapshot_json : [];
    }
}
