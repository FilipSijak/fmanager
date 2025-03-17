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
        $playerClub = $this->club()->first();
        $playerContract = $this->contract()->first();
        $attributeFields = array_merge(
            PlayerFields::TECHNICAL_FIELDS,
            PlayerFields::MENTAL_FIELDS,
            PlayerFields::PHYSICAL_FIELDS
        );

        $fields  = [
            'id' => $this->id,
            'club' => [
                'club_id' => $playerClub->id,
                'name' => $playerClub->name
            ],
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'potential' => $this->potential,
            'position' => $this->position,
            'country_code' => $this->country_code,
            'dob' => $this->dob,
            'technical' => $this->technical,
            'mental' => $this->mental,
            'physical' => $this->physical,
            'contract_start' => $playerContract ? $playerContract->contract_start : null,
            'contract_end' => $playerContract ? $playerContract->contract_end : null,
            'salary' => $this->salary,
        ];

        foreach ($attributeFields as $field) {
            $fields[$field] = $this->{$field};
        }

        return $fields;
    }
}
