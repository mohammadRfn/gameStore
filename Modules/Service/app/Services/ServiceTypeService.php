<?php

namespace Modules\Service\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Service\Models\ServiceJob;
use Modules\Service\Models\ServiceType;
use RuntimeException;

class ServiceTypeService
{
    public function getAllServiceTypes(): Collection
    {
        return ServiceType::all();
    }

    public function getServiceTypeById(int $id): ServiceType
    {
        return ServiceType::findOrFail($id);
    }

    public function createServiceType(array $data): ServiceType
    {
        return ServiceType::create($data);
    }

    public function updateServiceType(int $id, array $data): ServiceType
    {
        $serviceType = ServiceType::findOrFail($id);
        $serviceType->update($data);
        return $serviceType;
    }

    /** لیست برای صفحهٔ مدیریت؛ job_links_count = تعداد سرویس‌های فعالی که از این نوع استفاده کرده‌اند */
    public function listWithUsage(): Collection
    {
        return ServiceType::withCount(['jobLinks' => fn ($q) => $q->whereHas('serviceJob')])
            ->orderByDesc('id')
            ->get();
    }

    public function deleteServiceType(int $id): void
    {
        $type = ServiceType::findOrFail($id);

        $used = $type->jobLinks()->whereHas('serviceJob')->count()
            + ServiceJob::where('service_type_id', $id)->count();

        if ($used > 0) {
            throw new RuntimeException(
                "نوع سرویس «{$type->name}» روی {$used} سرویس استفاده شده و حذف نمی‌شود؛ به‌جای حذف، غیرفعالش کنید."
            );
        }

        $type->delete();
    }
}
