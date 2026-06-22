<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'platform'    => $this->platform,
            'category'    => $this->category,
            'base_price'  => $this->base_price,
            'description' => $this->description,
            'is_active'   => $this->is_active,
        ];
    }
}
