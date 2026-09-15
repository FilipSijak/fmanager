<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StadiumStandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'position' => $this->position?->value,
            'capacity' => $this->capacity,
            'status' => $this->status?->value,
            'construction' => new StadiumStandConstructionResource($this->whenLoaded('construction')),
        ];
    }
}
