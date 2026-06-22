<?php

namespace App\Services;

use App\Models\ServiceJob;

interface ServiceJobServiceInterface
{
    public function createServiceJob(array $data): ServiceJob;

    public function updateServiceJob(int $id, array $data): ServiceJob;

    public function deleteServiceJob(int $id): void;

    public function findServiceJob(int $id): ServiceJob;
}
