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
            'tackling' => $this->tackling?->value,
            'offside_trap' => $this->offside_trap,
            'counter_attack' => $this->counter_attack,
            'men_behind_ball' => $this->men_behind_ball,
            'free_kicks_left_player_id' => $this->free_kicks_left_player_id,
            'free_kicks_right_player_id' => $this->free_kicks_right_player_id,
            'corners_left_player_id' => $this->corners_left_player_id,
            'corners_right_player_id' => $this->corners_right_player_id,
            'playmaker_player_id' => $this->playmaker_player_id,
        ];
    }
}
