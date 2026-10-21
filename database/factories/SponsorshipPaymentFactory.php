<?php

namespace Database\Factories;

use App\EntityTransactionType;
use App\Models\Account;
use App\Models\FinanceTransactionEntity;
use App\Models\Season;
use App\Models\SponsorshipContract;
use App\Models\SponsorshipPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SponsorshipPayment> */
class SponsorshipPaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sponsorship_contract_id' => SponsorshipContract::factory(),
            'season_id' => fn (array $attributes): int => Season::factory()->create([
                'instance_id' => SponsorshipContract::query()->findOrFail($attributes['sponsorship_contract_id'])->instance_id,
            ])->id,
            'transaction_id' => function (array $attributes): int {
                $contract = SponsorshipContract::query()->findOrFail($attributes['sponsorship_contract_id']);
                $account = Account::query()->where('club_id', $contract->club_id)->first()
                    ?? Account::factory()->create(['club_id' => $contract->club_id]);

                return FinanceTransactionEntity::factory()->create([
                    'club_account_id' => $account->id,
                    'game_entity_account_id' => $contract->sponsor_account_id,
                    'event_type' => EntityTransactionType::SPONSORSHIP,
                    'event_id' => $contract->id,
                    'amount' => $attributes['amount'],
                    'transaction_date' => $attributes['paid_on'],
                ])->id;
            },
            'amount' => 15_000_000,
            'paid_on' => '2026-08-15',
        ];
    }
}
