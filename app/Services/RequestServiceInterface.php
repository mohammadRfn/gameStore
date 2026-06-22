<?php

namespace App\Services;

interface RequestServiceInterface
{
    public function createRequest(array $data);
    public function getAllItems();
    public function updateRequest(int $requestId, array $data);
    public function deleteRequest(int $requestId);
    public function showRequest(int $requestId);
}

