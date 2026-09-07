<?php

namespace Modules\Profile\Services;

use App\Models\StoreProfile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class StoreProfileService
{
    public function getAll(): Collection
    {
        return StoreProfile::query()
            ->orderBy('is_primary', 'desc')
            ->orderBy('updated_at', 'desc')
            ->get();
    }

    public function findById(int $id): StoreProfile
    {
        return StoreProfile::query()->findOrFail($id);
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
            $data = $this->applyUploadedFiles($data);

            return StoreProfile::create($data);
        });
    }

    public function update(int $id, array $data): StoreProfile
    {
        return DB::transaction(function () use ($id, $data) {
            $profile = StoreProfile::findOrFail($id);

            if (
                isset($data['slug']) && $data['slug'] !== $profile->slug
                && StoreProfile::where('slug', $data['slug'])->where('id', '!=', $id)->exists()
            ) {
                throw new RuntimeException('این شناسه (slug) قبلاً برای پروفایل دیگری ثبت شده است.');
            }

            if (! empty($data['is_primary'])) {
                StoreProfile::where('id', '!=', $id)->update(['is_primary' => false]);
            }

            $data = $this->applyUploadedFiles($data, $profile);
            $profile->update($data);

            return $profile->fresh();
        });
    }

    public function delete(int $id): void
    {
        $profile = StoreProfile::findOrFail($id);

        if ($profile->is_primary) {
            throw new RuntimeException('پروفایل اصلی را نمی‌توان حذف کرد؛ ابتدا پروفایل دیگری را اصلی کنید.');
        }

        $this->deleteStoredFile($profile->logo_path);
        $this->deleteStoredFile($profile->cover_path);

        $profile->delete();
    }

    public function setPrimary(int $id): StoreProfile
    {
        return DB::transaction(function () use ($id) {
            StoreProfile::where('id', '!=', $id)->update(['is_primary' => false]);

            $profile = StoreProfile::findOrFail($id);
            $profile->update(['is_primary' => true, 'status' => 'active']);

            return $profile->fresh();
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
    protected function applyUploadedFiles(array $data, ?StoreProfile $existing = null): array
    {
        if (! empty($data['remove_logo']) && $existing) {
            $this->deleteStoredFile($existing->logo_path);
            $data['logo_path'] = null;
        }

        if (! empty($data['remove_cover']) && $existing) {
            $this->deleteStoredFile($existing->cover_path);
            $data['cover_path'] = null;
        }

        if (! empty($data['logo']) && $data['logo'] instanceof UploadedFile) {
            if ($existing) {
                $this->deleteStoredFile($existing->logo_path);
            }
            $data['logo_path'] = $data['logo']->store('store-profiles/logos', 'public');
        }

        if (! empty($data['cover']) && $data['cover'] instanceof UploadedFile) {
            if ($existing) {
                $this->deleteStoredFile($existing->cover_path);
            }
            $data['cover_path'] = $data['cover']->store('store-profiles/covers', 'public');
        }

        unset($data['logo'], $data['cover'], $data['remove_logo'], $data['remove_cover']);

        return $data;
    }

    protected function deleteStoredFile(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
