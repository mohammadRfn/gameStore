<?php

namespace Modules\Customer\Http\Resources;

use App\Http\Resources\InvoiceResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Request\Http\Resources\RequestResource;

class CustomerResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'requests' => RequestResource::collection($this->requests),
            'invoices' => InvoiceResource::collection($this->invoices),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
