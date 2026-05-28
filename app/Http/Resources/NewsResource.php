<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NewsResource extends JsonResource
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
            'instance_id' => $this->instance_id,
            'season_id' => $this->season_id,
            'club_id' => $this->club_id,
            'competition_id' => $this->competition_id,
            'title' => $this->title,
            'content' => $this->content,
            'type' => $this->type,
            'priority' => $this->priority,
            'published_at' => $this->published_at,
            'is_read' => $this->is_read,
            'read_at' => $this->read_at,
        ];
    }
}
