<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\Instance;
use App\Models\Person;
use App\Models\Player;
use App\Services\PersonService\PersonConfig\Player\PlayerFields;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Player>
 */
class PlayerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $playerFields = array_merge(
            PlayerFields::TECHNICAL_FIELDS,
            PlayerFields::MENTAL_FIELDS,
            PlayerFields::PHYSICAL_FIELDS
        );

        $playerFieldsValues = [];

        foreach ($playerFields as $field) {
            $playerFieldsValues[$field] = random_int(1, 20);
        }

        return array_merge(
            [
                'instance_id' => Instance::factory()->make(['id' => 1])->id,
                'person_id' => Person::factory(),
                'club_id' => Club::factory()->make(['id' => 1])->id,
                'value' => random_int(100000, 100000000),
                'potential' => random_int(20, 200),
                'position' => 'CB',
            ],
            $playerFieldsValues
        );
    }
}
