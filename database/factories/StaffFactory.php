<?php

namespace Database\Factories;

use App\Models\Instance;
use App\Models\Person;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Staff>
 */
class StaffFactory extends Factory
{
    protected $model = Staff::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $attributeNames = [
            'attacking', 'defending', 'fitness', 'mental', 'tactical', 'technical',
            'working_with_youngsters', 'adaptability', 'determination', 'discipline',
            'man_management', 'motivating', 'judging_player_potential',
            'judging_player_ability', 'judging_staff_ability', 'negotiating', 'tactics',
            'distribution', 'handling', 'shot_stopping',
        ];

        return array_merge(
            [
                'instance_id' => Instance::factory()->make(['id' => 1])->id,
                'person_id' => Person::factory(),
                'type' => 'MANAGER',
            ],
            array_fill_keys($attributeNames, 10),
        );
    }
}
