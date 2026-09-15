<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StadiumStandConstructionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'target_capacity' => $this->target_capacity,
            'capacity_increase' => $this->capacity_increase,
            'started_at' => $this->started_at?->toDateString(),
            'completes_at' => $this->completes_at?->toDateString(),
        ];
    }
}
