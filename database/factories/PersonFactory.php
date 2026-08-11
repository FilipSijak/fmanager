<?php

namespace Database\Factories;

use App\Models\Instance;
use App\Models\Person;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    protected $model = Person::class;

    public function definition(): array
    {
        return [
            'instance_id' => Instance::factory()->make(['id' => 1])->id,
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'dob' => Carbon::now()->subYears(random_int(15, 70))->toDateString(),
            'country_code' => $this->faker->countryCode,
        ];
    }
}
