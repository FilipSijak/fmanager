<?php

namespace Database\Factories;

use App\Models\Instance;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Manager>
 */
class StaffFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $randomYear = random_int(1955, 1990);
        $randomMonth = random_int(1, 12);
        $randomDay = random_int(1, 30);

        return [
            'instance_id' => Instance::factory()->make()->id,
            'type' => 'MANAGER',
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'dob'=> Carbon::createFromDate($randomYear, $randomMonth, $randomDay)->format('Y-m-d')
        ];
    }
}
