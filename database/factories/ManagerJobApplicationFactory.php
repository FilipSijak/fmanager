<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\Instance;
use App\Models\ManagerJobApplication;
use App\Models\StaffCoaching;
use App\Services\ManagerJobService\ManagerJobApplicationStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ManagerJobApplication>
 */
class ManagerJobApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $instance = Instance::factory()->create();

        return [
            'instance_id' => $instance->id,
            'club_id' => Club::factory()->create(['instance_id' => $instance->id])->id,
            'staff_coaching_id' => StaffCoaching::factory()->create(['instance_id' => $instance->id, 'club_id' => null])->id,
            'applied_on' => $instance->instance_date,
            'status' => ManagerJobApplicationStatus::Pending,
        ];
    }
}
