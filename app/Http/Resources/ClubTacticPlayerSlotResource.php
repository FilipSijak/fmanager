<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubTacticPlayerSlotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slot' => $this->slot,
            'position' => $this->position,
            'player_id' => $this->player_id,
        ];
    }
}
