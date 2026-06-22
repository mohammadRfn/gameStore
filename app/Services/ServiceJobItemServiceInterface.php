<?php

namespace App\Services;

use App\Models\ServiceJobItem;
use Illuminate\Database\Eloquent\Collection;

interface ServiceJobItemServiceInterface
{
    /**
     */
    public function getItemsByServiceJob(int $serviceJobId): Collection;

    /**
     */
    public function createItemForServiceJob(array $data, int $serviceJobId): ServiceJobItem;

    /**
     */
    public function updateItemForServiceJob(int $id, array $data): ServiceJobItem;

    /**
     */
    public function deleteItem(int $id): void;

    /**
     */
    public function getItemById(int $id): ServiceJobItem;
}
