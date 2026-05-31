<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ClubResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'stadium' => new StadiumResource($this->stadium),
            'account' => new AccountResource($this->account),
            'rank' => $this->rank,
            'rank_academy' => $this->rank_academy,
            'rank_training' => $this->rank_training,
            'country_code' => $this->country_code
        ];
    }
}
