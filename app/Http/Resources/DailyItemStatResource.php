<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DailyItemStatResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'shop_id'              => $this->shop_id,
            'item_id'              => $this->item_id,
            'item_name'            => $this->whenLoaded('item', fn () => $this->item->name),
            'stat_date'            => $this->stat_date,
            'opening_stock'        => $this->opening_stock,
            'closing_stock'        => $this->closing_stock,
            'sold_quantity'        => $this->sold_quantity,
            'purchased_quantity'   => $this->purchased_quantity,
            'adjusted_in_quantity' => $this->adjusted_in_quantity,
            'adjusted_out_quantity'=> $this->adjusted_out_quantity,
            'revenue'              => $this->revenue,
            'total_cost'           => $this->total_cost,
            'profit'               => $this->profit,
        ];
    }
}
