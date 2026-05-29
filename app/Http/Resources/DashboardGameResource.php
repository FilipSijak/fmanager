<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardGameResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'match_start' => $this->match_start,
            'home_team' => [
                'id' => $this->hometeam_id,
                'name' => $this->home_team_name,
            ],
            'away_team' => [
                'id' => $this->awayteam_id,
                'name' => $this->away_team_name,
            ],
        ];
    }
}
