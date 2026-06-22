<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'categories' => $this->when($this->request && $this->request->categories, function () {
                return $this->request->categories->pluck('name'); 
            }),
            'total_amount' => $this->total_amount,
            'invoice_number' => $this->invoice_number,
            'customer_id' => $this->customer_id ?? null,
            'is_confirmed' => $this->is_confirmed,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'order_items' => OrderItemResource::collection($this->orderItems),
        ];
    }
}
