<?php

namespace Database\Factories;

use App\GameEntityType;
use App\Models\Club;
use App\Models\GameEntity;
use App\Models\GameEntityAccount;
use App\Models\Instance;
use App\Models\SponsorshipContract;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SponsorshipContract> */
class SponsorshipContractFactory extends Factory
{
    public function definition(): array
    {
        return [
            'instance_id' => Instance::factory(),
            'club_id' => fn (array $attributes): int => Club::factory()->create(['instance_id' => $attributes['instance_id']])->id,
            'sponsor_account_id' => fn (array $attributes): int => GameEntityAccount::factory()->create([
                'instance_id' => $attributes['instance_id'],
                'game_entity_id' => GameEntity::factory()->create(['instance_id' => $attributes['instance_id'], 'type' => GameEntityType::SPONSOR])->id,
            ])->id,
            'annual_amount' => 15_000_000,
            'starts_at' => '2026-08-15',
            'expires_at' => '2031-06-15',
        ];
    }
}
