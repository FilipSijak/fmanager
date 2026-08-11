<?php

namespace Database\Factories;

use App\Models\Instance;
use App\Models\Person;
use App\Models\StaffScout;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffScout>
 */
class StaffScoutFactory extends Factory
{
    protected $model = StaffScout::class;

    public function definition(): array
    {
        return [
            'instance_id' => Instance::factory()->make(['id' => 1])->id,
            'person_id' => Person::factory(),
            'region' => null,
            'judging_player_ability' => 10,
            'judging_player_potential' => 10,
            'tactical_knowledge' => 10,
            'data_analysis' => 10,
            'market_knowledge' => 10,
        ];
    }
}
