<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StadiumCommercialVenueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'size' => $this->size?->value,
            'build_cost' => $this->build_cost,
            'category' => new StadiumCommercialCategoryResource($this->whenLoaded('category')),
        ];
    }
}
