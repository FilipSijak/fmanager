<?php

namespace Database\Factories;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Instance>
 */
class InstanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'user_id' => User::factory()->create()->id,
            'club_id' => User::factory()->create()->id,
            'manager_id' => User::factory()->create()->id,
            'instance_date' => Carbon::create(),
            'instance_version' => $this->faker->numberBetween(1,10),
            'instance_hash' => $this->faker->randomAscii(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ];
    }
}
