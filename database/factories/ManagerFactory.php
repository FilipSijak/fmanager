<?php

namespace Database\Factories;

use App\Models\Instance;
use App\Models\Manager;
use App\Models\StaffCoaching;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Manager>
 */
class ManagerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $instance = Instance::factory()->create();

        return [
            'user_id' => $instance->user_id,
            'instance_id' => $instance->id,
            'staff_coaching_id' => StaffCoaching::factory()->create(['instance_id' => $instance->id])->id,
            'reputation' => random_int(10, 200),
        ];
    }
}
