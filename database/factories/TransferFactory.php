<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\Player;
use App\Models\Season;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transfer>
 */
class TransferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'season_id' => Season::factory()->create()->id,
            'source_club_id' => Club::factory()->create()->id,
            'target_club_id' => Club::factory()->create()->id,
            'player_id' => Player::factory()->create()->id,
            'offer_date' => Carbon::now(),
            'transfer_date' => Carbon::now()->addMonth(),
            'transfer_status' => 0,
            'transfer_type' => 1,
            'amount' => random_int(100000, 10000000),
            'loan_start' => null,
            'loan_end' => null
        ];
    }
}
