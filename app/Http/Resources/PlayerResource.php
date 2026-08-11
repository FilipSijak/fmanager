<?php

namespace App\Http\Resources;

use App\Services\PersonService\PersonConfig\Player\PlayerFields;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array|Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'position' => $this->position,
            'country_code' => $this->country_code,
            'dob' => $this->dob,
            'is_retired' => (bool) $this->is_retired,
            'club' => $this->club ? [
                'id' => $this->club->id,
                'name' => $this->club->name,
            ] : null,
            'contract' => $this->contract ? new PlayerContractResource($this->contract) : null,
            'attributes' => [
                'technical' => collect(PlayerFields::TECHNICAL_FIELDS)
                    ->mapWithKeys(fn ($field) => [$field => $this->{$field}])
                    ->all(),

                'mental' => collect(PlayerFields::MENTAL_FIELDS)
                    ->mapWithKeys(fn ($field) => [$field => $this->{$field}])
                    ->all(),

                'physical' => collect(PlayerFields::PHYSICAL_FIELDS)
                    ->mapWithKeys(fn ($field) => [$field => $this->{$field}])
                    ->all(),
            ],
        ];
    }
}
