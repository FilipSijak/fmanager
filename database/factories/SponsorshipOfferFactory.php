<?php

namespace Database\Factories;

use App\Models\Season;
use App\Models\SponsorshipContract;
use App\Models\SponsorshipOffer;
use App\Services\FinanceService\SponsorshipOfferStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SponsorshipOffer> */
class SponsorshipOfferFactory extends Factory
{
    public function definition(): array
    {
        return [
            'instance_id' => fn (): int => SponsorshipContract::factory()->create(['expires_at' => '2027-06-15'])->instance_id,
            'club_id' => fn (array $attributes): int => SponsorshipContract::query()->where('instance_id', $attributes['instance_id'])->firstOrFail()->club_id,
            'sponsor_account_id' => fn (array $attributes): int => SponsorshipContract::query()->where('instance_id', $attributes['instance_id'])->firstOrFail()->sponsor_account_id,
            'season_id' => fn (array $attributes): int => Season::factory()->create([
                'instance_id' => $attributes['instance_id'], 'start_date' => '2026-08-15', 'end_date' => '2027-06-15',
            ])->id,
            'status' => SponsorshipOfferStatus::Pending,
            'attempt_number' => 1, 'term_seasons' => 1,
            'annual_amount' => 3750000, 'maximum_annual_amount' => 4125000, 'negotiation_rounds' => 0,
            'offered_on' => '2026-12-15', 'starts_at' => '2027-08-15',
            'expires_at' => '2028-06-15', 'respond_by' => '2027-08-14',
        ];
    }
}
