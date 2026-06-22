<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'shop_id'      => $this->shop_id,
            'customer_id'  => $this->customer_id,
            'request_id'   => $this->request_id,
            'service_type' => $this->whenLoaded('serviceType', function () {
                return [
                    'id'   => $this->serviceType->id,
                    'name' => $this->serviceType->name,
                ];
            }),
            'customer' => $this->whenLoaded('customer', function () {
                return [
                    'id'   => $this->customer->id,
                    'name' => $this->customer->name ?? null,
                ];
            }),
            'device_type'   => $this->device_type,
            'device_serial' => $this->device_serial,

            'customer_problem_description' => $this->customer_problem_description,
            'diagnosis_description'        => $this->diagnosis_description,

            'status'         => $this->status,
            'invoice_id'     => $this->invoice_id,
            'estimated_price'=> $this->estimated_price,
            'final_price'    => $this->final_price,

            'received_at'  => $this->received_at,
            'started_at'   => $this->started_at,
            'completed_at' => $this->completed_at,
            'delivered_at' => $this->delivered_at,

            'items' => ServiceJobItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
