<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\Player;
use App\Services\TransferService\TransferTypes;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransferListFactory extends Factory
{
    public function definition()
    {
        return [
            'player_id' => Player::factory()->make(['id' => 1])->id,
            'club_id' => Club::factory()->make(['id' => 1])->id,
            'transfer_type' => TransferTypes::PERMANENT_TRANSFER,
            'transfer_fee' => $this->faker->randomNumber(4)
        ];
    }
}
