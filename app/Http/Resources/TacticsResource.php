<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TacticsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'formation' => new FormationResource($this->whenLoaded('formation')),
            'mentality' => $this->mentality?->value,
            'pressing' => $this->pressing?->value,
            'passing' => $this->passing?->value,
        ];
    }
}
