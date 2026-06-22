<?php

namespace App\Services;

interface CategoryServiceInterface
{
    public function createCategory(string $name);
    public function getAllCategories();
}

