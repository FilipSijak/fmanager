<?php

namespace App\Http\Resources;

use App\Models\Player;
use App\Models\Stadium;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'stadium' => new StadiumResource($this->stadium()->get()),
            'players' => PlayerResource::collection($this->players()->get())
        ];
    }
}
