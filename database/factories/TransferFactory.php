<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\Player;
use App\Models\Season;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransferFactory extends Factory
{
    public function definition()
    {
        return [
            'season_id' => Season::factory()->make(['id' => 1])->id,
            'source_club_id' => Club::factory()->make(['id' => 2])->id,
            'target_club_id' => Club::factory()->make(['id' => 1])->id,
            'player_id' => Player::factory()->make(['id' => 1])->id,
            'offer_date' => Carbon::now(),
            'transfer_date' => Carbon::now()->addMonth(),
            'transfer_status' => 1,
            'transfer_type' => 3,
            'amount' => 67000000,
            'loan_start' => null,
            'loan_end' => null
        ];
    }
}
