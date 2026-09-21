<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'tactical_tendency' => $this->tactical_tendency?->value,
            'description' => $this->description,
            'slots' => $this->whenLoaded('slots', fn (): array => $this->slots->map(fn ($slot): array => [
                'slot' => $slot->slot,
                'position' => $slot->position,
                'x' => $slot->x,
                'y' => $slot->y,
                'has_arrow' => $slot->has_arrow,
            ])->all()),
        ];
    }
}
