<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StadiumResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type?->value,
            'commercial_limit' => $this->commercial_limit,
            'capacity' => $this->capacity,
            'active_capacity' => $this->active_capacity,
            'stands' => StadiumStandResource::collection($this->whenLoaded('stands')),
            'commercial_venues' => StadiumCommercialVenueResource::collection($this->whenLoaded('commercialVenues')),
        ];
    }
}
