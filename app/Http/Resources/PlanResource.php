<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'name'                 => $this->name,
            'code'                 => $this->code,
            'monthly_price'        => $this->monthly_price,
            'yearly_price'         => $this->yearly_price,
            'has_inventory_module' => $this->has_inventory_module,
            'has_services_module'  => $this->has_services_module,
            'has_analytics_module' => $this->has_analytics_module,
            'max_users'            => $this->max_users,
            'max_items'            => $this->max_items,
            'is_active'            => $this->is_active,
        ];
    }
}
