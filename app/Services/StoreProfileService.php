<?php

namespace App\Services;

use App\Models\StoreProfile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StoreProfileService
{
    public function getAll(): Collection
    {
        return StoreProfile::with('shop')
            ->orderBy('is_primary', 'desc')
            ->orderBy('updated_at', 'desc')
            ->get();
    }

    public function findById(int $id): StoreProfile
    {
        return StoreProfile::with('shop')->findOrFail($id);
    }

    public function findPrimary(): ?StoreProfile
    {
        return StoreProfile::primary()->first();
    }

    public function findBySlug(string $slug): ?StoreProfile
    {
        return StoreProfile::where('slug', $slug)->first();
    }

    public function create(array $data): StoreProfile
    {
        return DB::transaction(function () use ($data) {
            if (StoreProfile::where('slug', $data['slug'])->exists()) {
                throw new RuntimeException('این شناسه (slug) قبلاً ثبت شده است.');
            }

            $wantPrimary = (bool) ($data['is_primary'] ?? false);

            // First profile ever → automatically primary
            if (StoreProfile::count() === 0) {
                $wantPrimary = true;
            }

            if ($wantPrimary) {
                StoreProfile::query()->update(['is_primary' => false]);
            }

            $data['is_primary'] = $wantPrimary;

            return StoreProfile::create($data);
        });
    }

    public function update(int $id, array $data): StoreProfile
    {
        return DB::transaction(function () use ($id, $data) {
            $profile = StoreProfile::findOrFail($id);

            if (isset($data['slug']) && $data['slug'] !== $profile->slug
                && StoreProfile::where('slug', $data['slug'])->where('id', '!=', $id)->exists()) {
                throw new RuntimeException('این شناسه (slug) قبلاً برای پروفایل دیگری ثبت شده است.');
            }

            if (! empty($data['is_primary'])) {
                StoreProfile::where('id', '!=', $id)->update(['is_primary' => false]);
            }

            $profile->update($data);

            return $profile->fresh('shop');
        });
    }

    public function delete(int $id): void
    {
        $profile = StoreProfile::findOrFail($id);

        if ($profile->is_primary) {
            throw new RuntimeException('پروفایل اصلی را نمی‌توان حذف کرد؛ ابتدا پروفایل دیگری را اصلی کنید.');
        }

        $profile->delete();
    }

    public function setPrimary(int $id): StoreProfile
    {
        return DB::transaction(function () use ($id) {
            StoreProfile::where('id', '!=', $id)->update(['is_primary' => false]);

            $profile = StoreProfile::findOrFail($id);
            $profile->update(['is_primary' => true, 'status' => 'active']);

            return $profile->fresh('shop');
        });
    }

    public function search(string $term): Collection
    {
        return StoreProfile::where('legal_name', 'like', "%{$term}%")
            ->orWhere('brand_name', 'like', "%{$term}%")
            ->orWhere('slug', 'like', "%{$term}%")
            ->orWhere('phone', 'like', "%{$term}%")
            ->orWhere('email', 'like', "%{$term}%")
            ->orderBy('updated_at', 'desc')
            ->get();
    }
}
