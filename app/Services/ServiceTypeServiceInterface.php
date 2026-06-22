<?php

namespace App\Services;

use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Collection;

interface ServiceTypeServiceInterface
{
    /**
     */
    public function getAllServiceTypes(): Collection;

    /**
     */
    public function createServiceType(array $data): ServiceType;

    /**
     */
    public function updateServiceType(int $id, array $data): ServiceType;

    /**
     */
    public function deleteServiceType(int $id): void;

    /**
     */
    public function getServiceTypeById(int $id): ServiceType;
}
