<?php

namespace Database\Factories;

use App\Models\Instance;
use App\Models\Person;
use App\Models\StaffPhysio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffPhysio>
 */
class StaffPhysioFactory extends Factory
{
    protected $model = StaffPhysio::class;

    public function definition(): array
    {
        return [
            'instance_id' => Instance::factory()->make(['id' => 1])->id,
            'person_id' => Person::factory(),
            'team_type' => 'FIRST_TEAM',
            'physiotherapy' => 10,
            'injury_prevention' => 10,
            'rehabilitation' => 10,
            'sports_science' => 10,
            'fitness_assessment' => 10,
        ];
    }
}
