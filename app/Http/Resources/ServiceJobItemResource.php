<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceJobItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'item_id'     => $this->item_id,
            'item_name'   => $this->whenLoaded('item', fn () => $this->item->name),
            'quantity'    => $this->quantity,
            'unit_price'  => $this->unit_price,
            'total_price' => $this->total_price,
        ];
    }
}
