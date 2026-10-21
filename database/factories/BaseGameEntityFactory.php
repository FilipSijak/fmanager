<?php

namespace Database\Factories;

use App\GameEntityType;
use App\Models\BaseData\BaseGameEntity;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BaseGameEntity> */
class BaseGameEntityFactory extends Factory
{
    protected $model = BaseGameEntity::class;

    public function definition(): array
    {
        return [
            'type' => GameEntityType::SPONSOR,
            'name' => fake()->unique()->company(),
            'rank' => 10,
            'starting_balance' => 10_000_000_000,
        ];
    }
}
