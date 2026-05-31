<?php

namespace App\Http\Resources;

use App\Services\PersonService\PersonConfig\Player\PlayerFields;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
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
            'club' => $this->club ? [
                'id' => $this->club->id,
                'name' => $this->club->name,
            ] : null,
            'contract' => new PlayerContractResource($this->contract),
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
