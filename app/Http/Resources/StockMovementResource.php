<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'shop_id'       => $this->shop_id,
            'item_id'       => $this->item_id,
            'invoice_id'    => $this->invoice_id,
            'service_job_id'=> $this->service_job_id,
            'movement_type' => $this->movement_type,
            'quantity'      => $this->quantity,
            'unit_cost'     => $this->unit_cost,
            'reason'        => $this->reason,
            'note'          => $this->note,
            'created_at'    => $this->created_at,
        ];
    }
}
