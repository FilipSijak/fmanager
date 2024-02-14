<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\Instance;
use App\Services\PersonService\PersonConfig\Player\PlayerFields;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Player>
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
            PlayerFields::TEHNICAL_FIELDS,
            PlayerFields::MENTAL_FIELDS,
            PlayerFields::PHYSICAL_FILDS
        );

        $playerFieldsValues = [];

        foreach ($playerFields as $field) {
            $playerFieldsValues[$field] = random_int(1, 20);
        }

        return array_merge
        (
            [
                'instance_id' => Instance::factory()->make(['id' => 1])->id,
                'club_id' => Club::factory()->make(['id' => 1])->id,
                'value' => random_int(100000, 100000000),
                'first_name' => $this->faker->firstName,
                'last_name' => $this->faker->lastName,
                'potential' => random_int(20,200),
                'position' => 'CB',
                'country_code' => $this->faker->countryCode,
                'dob' => Carbon::now()->subYears(random_int(15, 42)),
            ],
            $playerFieldsValues
        );
    }
}
