<?php

namespace App\Services;

interface ItemServiceInterface
{
    public function createItem(array $data);
    public function getAllItems();
    public function findItem(int $id);
    public function updateItem(int $id, array $data);
    public function deleteItem(int $id): void;
}
