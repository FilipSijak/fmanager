<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Club;
use App\Models\Instance;
use App\Models\Player;
use App\Models\PlayerContract;
use App\Models\Season;
use App\Models\Transfer;
use App\Models\TransferContractOffer;
use App\Models\TransferFinancialDetails;
use App\Services\TransferService\TransferServiceHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class TransferManagementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guests_cannot_manage_transfers(): void
    {
        $this->get('/transfers')->assertRedirect('/login');
        $this->postJson('/api/transfers', [])->assertUnauthorized();
        $this->postJson('/api/transfers/1/respond', ['action' => 'accept'])->assertUnauthorized();
    }

    #[Test]
    public function the_centre_separates_active_deals_and_history_and_excludes_other_clubs(): void
    {
        [$instance, $buyer, $seller, $player] = $this->world();
        $active = $this->deal($instance, $buyer, $seller, $player, 7);
        $closed = $this->deal($instance, $buyer, $seller, $player, 15);
        $other = Club::factory()->create(['instance_id' => $instance->id, 'stadium_id' => 999]);
        $this->deal($instance, $other, $seller, $player, 1);

        $this->withoutVite()->get('/transfers')->assertInertia(fn (Assert $page) => $page
            ->component('transfers/Transfers')->where('club.id', $buyer->id)->where('transferBudget', 1000000)
            ->has('activeTransfers', 1)->where('activeTransfers.0.id', $active->id)
            ->where('activeTransfers.0.direction', 'incoming')->where('activeTransfers.0.actions', ['accept', 'counter', 'reject'])
            ->has('history.data', 1)->where('history.data.0.id', $closed->id)->where('history.data.0.actions', []));
    }

    #[Test]
    public function the_offer_page_exposes_the_selected_player_and_budget(): void
    {
        [, , $seller, $player] = $this->world();

        $this->withoutVite()->get('/transfers/offer/'.$player->id)->assertInertia(fn (Assert $page) => $page
            ->component('transfers/PlayerOffer')->where('player.id', $player->id)
            ->where('player.club.id', $seller->id)->where('transferBudget', 1000000));
    }

    #[Test]
    public function permanent_offers_derive_both_clubs_on_the_server(): void
    {
        [$instance, $buyer, $seller, $player] = $this->world();

        $this->postJson('/api/transfers', [
            'player_id' => $player->id, 'transfer_type' => 1, 'amount' => 150000, 'installments' => 12,
            'source_club_id' => $seller->id, 'target_club_id' => $buyer->id, 'transfer_status' => 14,
        ])->assertCreated();

        $this->assertDatabaseHas('transfers', ['instance_id' => $instance->id, 'source_club_id' => $buyer->id, 'target_club_id' => $seller->id, 'player_id' => $player->id, 'transfer_status' => 1]);
        $this->assertDatabaseHas('transfer_financial_details', ['amount' => 150000, 'installments' => 12]);
    }

    #[Test]
    public function free_agents_receive_the_submitted_salary_and_bonuses(): void
    {
        [$instance, $buyer, , $player] = $this->world();
        $player->forceFill(['club_id' => null])->save();

        $this->postJson('/api/transfers', ['player_id' => $player->id, 'transfer_type' => 3, 'salary' => 2500, 'appearance' => 200, 'clean_sheet' => 300, 'transfer_fee' => 10000])->assertCreated();

        $this->assertDatabaseHas('transfers', ['source_club_id' => $buyer->id, 'target_club_id' => null, 'transfer_status' => 2, 'transfer_type' => 3]);
        $this->assertDatabaseHas('transfer_contract_offers', ['salary' => 2500, 'appearance' => 200, 'clean_sheet' => 300, 'transfer_fee' => 10000]);
    }

    #[Test]
    public function a_free_agent_without_a_previous_contract_can_complete_the_negotiation(): void
    {
        [, $buyer, , $player] = $this->world();
        $player->forceFill(['club_id' => null, 'contract_id' => null])->save();
        $response = $this->postJson('/api/transfers', ['player_id' => $player->id, 'transfer_type' => 3, 'salary' => 2500]);
        $response->assertCreated();
        $transfer = Transfer::query()->findOrFail($response->json('data.id'));
        $handler = app(TransferServiceHandler::class);

        $handler->processTransfer($transfer);
        $this->assertSame(3, $transfer->fresh()->transfer_status);
        $handler->processTransfer($transfer->fresh());
        $handler->processTransfer($transfer->fresh());

        $this->assertSame(14, $transfer->fresh()->transfer_status);
        $this->assertSame($buyer->id, $player->fresh()->club_id);
        $this->assertDatabaseHas('players_contracts', ['id' => $player->fresh()->contract_id, 'salary' => 2500, 'contract_start' => '2026-07-15', 'contract_end' => '2029-07-15']);
    }

    #[Test]
    public function closed_transfer_history_is_paginated(): void
    {
        [$instance, $buyer, $seller, $player] = $this->world();
        Transfer::factory()->count(21)->create(['instance_id' => $instance->id, 'season_id' => $instance->season_id, 'source_club_id' => $buyer->id, 'target_club_id' => $seller->id, 'player_id' => $player->id, 'transfer_status' => 15]);

        $this->withoutVite()->get('/transfers?page=2')->assertInertia(fn (Assert $page) => $page
            ->has('history.data', 1)->where('history.current_page', 2)->where('history.total', 21));
    }

    #[Test]
    public function offers_require_a_player_type_and_fee(): void
    {
        $this->world();

        $this->postJson('/api/transfers', [])->assertUnprocessable()->assertJsonValidationErrors(['player_id', 'transfer_type']);
        $this->postJson('/api/transfers/1/respond', ['action' => 'counter'])->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->postJson('/api/transfers/1/respond', ['action' => 'contract'])->assertUnprocessable()->assertJsonValidationErrors('salary');
    }

    #[Test]
    public function a_player_who_has_left_the_selling_club_cannot_be_negotiated_for(): void
    {
        [$instance, $buyer, $seller, $player] = $this->world();
        $transfer = $this->deal($instance, $buyer, $seller, $player, 7);
        $player->forceFill(['club_id' => null])->save();

        $this->postJson($this->respondUrl($transfer), ['action' => 'accept'])->assertUnprocessable()->assertJsonValidationErrors('action');
        $this->assertSame(7, $transfer->fresh()->transfer_status);
    }

    #[Test]
    #[TestWith(['amount', -1])]
    #[TestWith(['amount', 2000000001])]
    #[TestWith(['amount', 1.5])]
    #[TestWith(['installments', 61])]
    public function invalid_financial_terms_return_validation_errors(string $field, int|float $value): void
    {
        [, , , $player] = $this->world();
        $data = ['player_id' => $player->id, 'transfer_type' => 1, 'amount' => 100000, 'installments' => 0];
        $data[$field] = $value;

        $this->postJson('/api/transfers', $data)->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('transfers', 0);
    }

    #[Test]
    public function offers_cannot_exceed_the_transfer_budget(): void
    {
        [, , , $player] = $this->world();

        $this->postJson('/api/transfers', ['player_id' => $player->id, 'transfer_type' => 1, 'amount' => 1000001])
            ->assertUnprocessable()->assertJsonPath('errors.amount.0', 'The offer exceeds your available transfer budget.');
    }

    #[Test]
    public function a_second_active_offer_for_the_same_player_is_refused(): void
    {
        [$instance, $buyer, $seller, $player] = $this->world();
        $this->deal($instance, $buyer, $seller, $player);

        $this->postJson('/api/transfers', ['player_id' => $player->id, 'transfer_type' => 1, 'amount' => 100000])
            ->assertUnprocessable()->assertJsonValidationErrors('player_id');
        $this->assertDatabaseCount('transfers', 1);
    }

    #[Test]
    public function a_contracted_player_cannot_be_signed_as_a_free_agent(): void
    {
        [, , , $player] = $this->world();

        $this->postJson('/api/transfers', ['player_id' => $player->id, 'transfer_type' => 3, 'salary' => 1000])
            ->assertUnprocessable()->assertJsonValidationErrors('transfer_type');
    }

    #[Test]
    #[TestWith(['own'])]
    #[TestWith(['retired'])]
    #[TestWith(['loan'])]
    public function unavailable_players_cannot_receive_offers(string $reason): void
    {
        [, $buyer, , $player] = $this->world();
        $player->forceFill(match ($reason) {
            'own' => ['club_id' => $buyer->id], 'retired' => ['is_retired' => true], 'loan' => ['loan_club_id' => $buyer->id],
        })->save();

        $this->postJson('/api/transfers', ['player_id' => $player->id, 'transfer_type' => 1, 'amount' => 100000])
            ->assertUnprocessable()->assertJsonValidationErrors('player_id');
    }

    #[Test]
    public function accepting_a_club_counteroffer_waits_for_the_managers_contract(): void
    {
        [$instance, $buyer, $seller, $player] = $this->world();
        $transfer = $this->deal($instance, $buyer, $seller, $player, 7);

        $this->postJson($this->respondUrl($transfer), ['action' => 'accept'])->assertOk();

        $this->assertSame(11, $transfer->fresh()->transfer_status);
        $this->assertDatabaseHas('transfer_contract_offers', ['transfer_id' => $transfer->id]);
        app(TransferServiceHandler::class)->processTransfer($transfer->fresh());
        $this->assertSame(11, $transfer->fresh()->transfer_status);

        $this->postJson($this->respondUrl($transfer), ['action' => 'contract', 'salary' => 9000, 'appearance' => 400, 'transfer_fee' => 0])->assertOk();
        $this->assertSame(2, $transfer->fresh()->transfer_status);
        $this->assertDatabaseHas('transfer_contract_offers', ['transfer_id' => $transfer->id, 'salary' => 9000, 'appearance' => 400]);
    }

    #[Test]
    public function the_buyer_can_send_a_revised_fee_to_the_seller(): void
    {
        [$instance, $buyer, $seller, $player] = $this->world();
        $transfer = $this->deal($instance, $buyer, $seller, $player, 7);

        $this->postJson($this->respondUrl($transfer), ['action' => 'counter', 'amount' => 90000])->assertOk();

        $this->assertSame(1, $transfer->fresh()->transfer_status);
        $this->assertDatabaseHas('transfer_financial_details', ['transfer_id' => $transfer->id, 'amount' => 90000]);
    }

    #[Test]
    public function incoming_bids_for_the_managers_players_wait_for_a_decision_and_can_be_countered(): void
    {
        [$instance, $buyer, $seller, $player] = $this->world();
        $instance->forceFill(['club_id' => $seller->id])->save();
        $transfer = $this->deal($instance, $buyer, $seller, $player);
        app(TransferServiceHandler::class)->processTransfer($transfer);
        $this->assertSame(1, $transfer->fresh()->transfer_status);

        $this->postJson($this->respondUrl($transfer), ['action' => 'counter', 'amount' => 200000])->assertOk();

        $this->assertSame(7, $transfer->fresh()->transfer_status);
        $this->assertDatabaseHas('transfer_financial_details', ['transfer_id' => $transfer->id, 'amount' => 200000]);
    }

    #[Test]
    public function the_seller_can_accept_a_bid_and_let_the_ai_buyer_offer_a_contract(): void
    {
        [$instance, $buyer, $seller, $player] = $this->world();
        $instance->forceFill(['club_id' => $seller->id])->save();
        $transfer = $this->deal($instance, $buyer, $seller, $player);

        $this->postJson($this->respondUrl($transfer), ['action' => 'accept'])->assertOk();

        $this->assertSame(2, $transfer->fresh()->transfer_status);
        $this->assertDatabaseHas('transfer_contract_offers', ['transfer_id' => $transfer->id]);
    }

    #[Test]
    public function the_seller_can_reject_a_bid(): void
    {
        [$instance, $buyer, $seller, $player] = $this->world();
        $instance->forceFill(['club_id' => $seller->id])->save();
        $transfer = $this->deal($instance, $buyer, $seller, $player);

        $this->postJson($this->respondUrl($transfer), ['action' => 'reject'])->assertOk();
        $this->assertSame(13, $transfer->fresh()->transfer_status);
    }

    #[Test]
    public function player_counteroffers_wait_and_can_be_accepted(): void
    {
        [$instance, $buyer, $seller, $player] = $this->world();
        $transfer = $this->deal($instance, $buyer, $seller, $player, 9);
        TransferContractOffer::factory()->create(['transfer_id' => $transfer->id, 'salary' => 4000]);
        app(TransferServiceHandler::class)->processTransfer($transfer);
        $this->assertSame(9, $transfer->fresh()->transfer_status);

        $this->postJson($this->respondUrl($transfer), ['action' => 'accept'])->assertOk();
        $this->assertSame(3, $transfer->fresh()->transfer_status);
    }

    #[Test]
    public function player_terms_can_be_countered_and_evaluated_on_the_next_day(): void
    {
        [$instance, $buyer, $seller, $player] = $this->world();
        $transfer = $this->deal($instance, $buyer, $seller, $player, 9);
        TransferContractOffer::factory()->create(['transfer_id' => $transfer->id, 'salary' => 4000]);

        $this->postJson($this->respondUrl($transfer), ['action' => 'contract', 'salary' => 3000])->assertOk();
        $this->assertSame(2, $transfer->fresh()->transfer_status);
        $this->assertDatabaseHas('transfer_contract_offers', ['transfer_id' => $transfer->id, 'salary' => 3000]);
    }

    #[Test]
    public function withdrawn_deals_stay_in_history_after_daily_processing(): void
    {
        [$instance, $buyer, $seller, $player] = $this->world();
        $transfer = $this->deal($instance, $buyer, $seller, $player);

        $this->postJson($this->respondUrl($transfer), ['action' => 'withdraw'])->assertOk();
        app(TransferServiceHandler::class)->processTransfer($transfer->fresh());

        $this->assertDatabaseHas('transfers', ['id' => $transfer->id, 'transfer_status' => 15]);
    }

    #[Test]
    public function accepting_a_counteroffer_rechecks_the_budget(): void
    {
        [$instance, $buyer, $seller, $player] = $this->world();
        $transfer = $this->deal($instance, $buyer, $seller, $player, 7);
        $buyer->account()->update(['transfer_budget' => 100]);

        $this->postJson($this->respondUrl($transfer), ['action' => 'accept'])->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->assertSame(7, $transfer->fresh()->transfer_status);
    }

    #[Test]
    #[TestWith([14])]
    #[TestWith([15])]
    #[TestWith([3])]
    public function stale_or_committed_deals_cannot_be_changed(int $status): void
    {
        [$instance, $buyer, $seller, $player] = $this->world();
        $transfer = $this->deal($instance, $buyer, $seller, $player, $status);

        $this->postJson($this->respondUrl($transfer), ['action' => 'withdraw'])->assertUnprocessable()->assertJsonValidationErrors('action');
        $this->assertSame($status, $transfer->fresh()->transfer_status);
    }

    #[Test]
    public function deals_from_other_seasons_cannot_be_changed(): void
    {
        [$instance, $buyer, $seller, $player] = $this->world();
        $transfer = $this->deal($instance, $buyer, $seller, $player, 7);
        $transfer->forceFill(['season_id' => $instance->season_id + 1])->save();

        $this->postJson($this->respondUrl($transfer), ['action' => 'accept'])->assertUnprocessable()->assertJsonValidationErrors('action');
    }

    #[Test]
    public function another_saves_players_and_transfers_are_not_found(): void
    {
        [$instance, $buyer, $seller, $player] = $this->world();
        $transfer = $this->deal($instance, $buyer, $seller, $player, 7);
        $otherInstance = Instance::factory()->create(['season_id' => $instance->season_id]);
        $player->forceFill(['instance_id' => $otherInstance->id])->save();
        $transfer->forceFill(['instance_id' => $otherInstance->id])->save();

        $this->postJson($this->respondUrl($transfer), ['action' => 'accept'])->assertNotFound();
        $this->postJson('/api/transfers', ['player_id' => $player->id, 'transfer_type' => 1, 'amount' => 100])->assertNotFound();
        $this->get('/transfers/offer/'.$player->id)->assertNotFound();
    }

    #[Test]
    public function negotiations_between_other_clubs_cannot_be_changed(): void
    {
        [$instance, , $seller, $player] = $this->world();
        $other = Club::factory()->create(['instance_id' => $instance->id, 'stadium_id' => 999]);
        $transfer = $this->deal($instance, $other, $seller, $player, 7);

        $this->postJson($this->respondUrl($transfer), ['action' => 'accept'])->assertNotFound();
    }

    #[Test]
    public function unemployed_managers_cannot_make_or_respond_to_offers(): void
    {
        [$instance] = $this->world();
        $instance->forceFill(['club_id' => null])->save();

        $this->postJson('/api/transfers', [])->assertForbidden();
        $this->postJson('/api/transfers/1/respond', ['action' => 'accept'])->assertForbidden();
        $this->get('/transfers')->assertRedirect('/');
    }

    /** @return array{Instance, Club, Club, Player} */
    private function world(): array
    {
        $instance = Instance::factory()->create(['instance_date' => '2026-07-15']);
        $season = Season::factory()->create(['instance_id' => $instance->id]);
        $buyer = Club::factory()->create(['instance_id' => $instance->id, 'stadium_id' => 100, 'rank' => 10]);
        $seller = Club::factory()->create(['instance_id' => $instance->id, 'stadium_id' => 200, 'rank' => 10]);
        Account::factory()->create(['club_id' => $buyer->id, 'transfer_budget' => 1000000]);
        $contract = PlayerContract::factory()->create(['salary' => 1000]);
        $player = Player::factory()->create(['instance_id' => $instance->id, 'club_id' => $seller->id, 'contract_id' => $contract->id, 'ambition' => 5, 'potential' => 100, 'marketing_rank' => 50]);
        $instance->forceFill(['club_id' => $buyer->id, 'season_id' => $season->id])->save();
        $this->actingAs($instance->user)->withHeaders(['instanceHash' => $instance->instance_hash])->withSession(['active_instance_hash' => $instance->instance_hash]);

        return [$instance, $buyer, $seller, $player];
    }

    private function deal(Instance $instance, Club $buyer, Club $seller, Player $player, int $status = 1): Transfer
    {
        $transfer = Transfer::factory()->create(['instance_id' => $instance->id, 'season_id' => $instance->season_id, 'source_club_id' => $buyer->id, 'target_club_id' => $seller->id, 'player_id' => $player->id, 'transfer_type' => 1, 'transfer_status' => $status]);
        TransferFinancialDetails::factory()->create(['transfer_id' => $transfer->id, 'amount' => 100000]);

        return $transfer;
    }

    private function respondUrl(Transfer $transfer): string
    {
        return '/api/transfers/'.$transfer->id.'/respond';
    }
}
