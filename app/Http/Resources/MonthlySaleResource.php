<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MonthlySaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'shop_id'            => $this->shop_id,
            'year'               => $this->year,
            'month'              => $this->month,
            'total_invoices'     => $this->total_invoices,
            'confirmed_invoices' => $this->confirmed_invoices,
            'total_revenue'      => $this->total_revenue,
            'products_revenue'   => $this->products_revenue,
            'services_revenue'   => $this->services_revenue,
            'total_cost'         => $this->total_cost,
            'profit'             => $this->profit,
            'unique_customers'   => $this->unique_customers,
            'new_customers'      => $this->new_customers,
        ];
    }
}
