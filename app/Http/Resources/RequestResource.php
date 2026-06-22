<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $categoryIds = is_array($this->category_id) ? $this->category_id : [$this->category_id];

        $categories = Category::whereIn('id', $categoryIds)->pluck('name')->toArray();

        if (empty($categories)) {
            $categories = [];
        }

        return [
            'id' => $this->id,
            'customer_name' => $this->customer_name,
            'customer_id' => $this->customer_id ?? null,
            'categories' => $categories,
            'description' => $this->description,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
