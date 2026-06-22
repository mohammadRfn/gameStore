<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShopResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'slug'                => $this->slug,
            'owner_user_id'       => $this->owner_user_id,
            'plan'                => $this->whenLoaded('plan', fn () => new PlanResource($this->plan)),
            'is_active'           => $this->is_active,
            'subscription_ends_at'=> $this->subscription_ends_at,
            'settings'            => $this->settings,
        ];
    }
}
