<?php

namespace Database\Factories;

use App\GameEntityType;
use App\Models\GameEntity;
use App\Models\Instance;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GameEntity> */
class GameEntityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'instance_id' => Instance::factory(),
            'type' => GameEntityType::BANK,
            'name' => 'Game Bank',
        ];
    }
}
