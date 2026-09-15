<?php

namespace Database\Factories;

use App\Models\GameEntity;
use App\Models\GameEntityAccount;
use App\Models\Instance;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GameEntityAccount> */
class GameEntityAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'game_entity_id' => GameEntity::factory(),
            'instance_id' => Instance::factory(),
            'balance' => 0,
            'future_balance' => 0,
            'allowed_debt' => 0,
        ];
    }
}
