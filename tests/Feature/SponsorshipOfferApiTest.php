<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Instance;
use App\Models\Season;
use App\Models\SponsorshipContract;
use App\Models\SponsorshipOffer;
use App\Services\FinanceService\SponsorshipOfferService;
use App\Services\FinanceService\SponsorshipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SponsorshipOfferApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function negotiations_keep_one_offer_and_acceptance_signs_once_without_immediate_payment(): void
    {
        $offer = $this->offer();
        $this->getJson('/api/finance/sponsor-offers')->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.maximum_annual_amount');
        $this->postJson($this->url($offer, 'negotiate'), ['annual_amount' => 3900000])
            ->assertOk()->assertJsonPath('data.annual_amount', 3900000)->assertJsonPath('data.negotiation_response', 'agreed');
        $this->postJson($this->url($offer, 'negotiate'), ['annual_amount' => 30000000])
            ->assertOk()->assertJsonPath('data.annual_amount', 4095000)->assertJsonPath('data.negotiation_response', 'counteroffer');
        $this->postJson($this->url($offer, 'negotiate'), ['annual_amount' => 30000000])
            ->assertOk()->assertJsonPath('data.annual_amount', 4125000);
        $this->assertDatabaseCount('sponsorship_offers', 1);
        $this->assertDatabaseHas('sponsorship_offers', ['id' => $offer->id, 'status' => 'pending', 'attempt_number' => 1, 'negotiation_rounds' => 3]);

        $this->postJson($this->url($offer, 'accept'))->assertOk()->assertJsonPath('data.status', 'accepted');
        $this->postJson($this->url($offer, 'accept'))->assertOk();
        $this->assertDatabaseCount('sponsorship_contracts', 2);
        $this->assertDatabaseHas('sponsorship_contracts', ['id' => $offer->fresh()->accepted_contract_id, 'annual_amount' => 4125000, 'starts_at' => '2027-08-15']);
        $this->assertDatabaseCount('sponsorship_payments', 0);
        $this->getJson('/api/finance/sponsor-offers')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson($this->url($offer, 'reject'))->assertUnprocessable();
    }

    #[Test]
    public function rejected_and_expired_offers_cannot_be_accepted_or_negotiated(): void
    {
        $offer = $this->offer();
        $this->postJson($this->url($offer, 'reject'))->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->postJson($this->url($offer, 'accept'))->assertUnprocessable();
        $this->postJson($this->url($offer, 'negotiate'), ['annual_amount' => 4000000])->assertUnprocessable();
        $offer->update(['status' => 'pending', 'respond_by' => '2026-12-14']);
        $this->getJson('/api/finance/sponsor-offers')->assertJsonCount(0, 'data');
        $this->postJson($this->url($offer, 'accept'))->assertUnprocessable();
    }

    #[Test]
    public function counteroffers_are_validated_and_cannot_change_protected_terms(): void
    {
        $offer = $this->offer();
        foreach ([null, 0, 1.5, 30000001] as $amount) {
            $this->postJson($this->url($offer, 'negotiate'), ['annual_amount' => $amount])->assertUnprocessable()->assertJsonValidationErrors('annual_amount');
        }
        $this->postJson($this->url($offer, 'negotiate'), ['annual_amount' => $offer->annual_amount])->assertUnprocessable();
        $this->postJson($this->url($offer, 'negotiate'), ['annual_amount' => 4000000, 'starts_at' => '2026-12-15', 'status' => 'accepted', 'maximum_annual_amount' => 30000000])
            ->assertOk()->assertJsonPath('data.starts_at', '2027-08-15')->assertJsonPath('data.status', 'pending');
        $this->assertSame(4125000, $offer->fresh()->maximum_annual_amount);
    }

    #[Test]
    public function another_games_offers_and_other_clubs_offers_are_inaccessible(): void
    {
        $offer = $this->offer();
        $foreign = SponsorshipOffer::factory()->create();
        foreach (['accept', 'reject', 'negotiate'] as $action) {
            $this->postJson($this->url($foreign, $action), ['annual_amount' => 4000000])->assertNotFound();
        }
        $contract = SponsorshipContract::factory()->create(['instance_id' => $offer->instance_id]);
        $otherClub = SponsorshipOffer::factory()->create(['instance_id' => $offer->instance_id, 'club_id' => $contract->club_id, 'sponsor_account_id' => $contract->sponsor_account_id]);
        $this->postJson($this->url($otherClub, 'accept'))->assertNotFound();
        $this->getJson('/api/finance/sponsor-offers')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $offer->id);
    }

    #[Test]
    public function an_overlapping_signed_contract_prevents_acceptance(): void
    {
        $offer = $this->offer();
        SponsorshipContract::factory()->create(['instance_id' => $offer->instance_id, 'club_id' => $offer->club_id, 'sponsor_account_id' => $offer->sponsor_account_id, 'starts_at' => $offer->starts_at, 'expires_at' => $offer->expires_at]);
        $this->postJson($this->url($offer, 'accept'))->assertUnprocessable();
        $this->assertDatabaseHas('sponsorship_offers', ['id' => $offer->id, 'status' => 'pending']);
        $this->assertDatabaseCount('sponsorship_contracts', 2);
    }

    #[Test]
    public function guests_cannot_view_or_respond_to_offers(): void
    {
        $this->getJson('/api/finance/sponsor-offers')->assertUnauthorized();
        $this->postJson('/api/finance/sponsor-offers/1/accept')->assertUnauthorized();
    }

    #[Test]
    public function the_finance_screen_exposes_the_managed_clubs_pending_offer(): void
    {
        $offer = $this->offer();
        $instance = Instance::query()->findOrFail($offer->instance_id);
        $this->withoutVite();
        $this->withSession(['active_instance_hash' => $instance->instance_hash])->get('/finance')
            ->assertInertia(fn (Assert $page) => $page->component('Finance')
                ->has('sponsorOffers', 1)->where('sponsorOffers.0.id', $offer->id)
                ->where('sponsorOffers.0.annual_amount', 3750000)
                ->missing('sponsorOffers.0.maximum_annual_amount'));
    }

    #[Test]
    public function the_accepted_contract_pays_only_on_its_season_start(): void
    {
        $offer = $this->offer();
        $instance = Instance::query()->findOrFail($offer->instance_id);
        $account = Account::factory()->create(['club_id' => $offer->club_id, 'balance' => 0, 'future_balance' => 0]);
        $offer->sponsorAccount->update(['balance' => 100000000, 'future_balance' => 100000000]);
        $this->postJson($this->url($offer, 'accept'))->assertOk();
        $season = Season::factory()->create(['instance_id' => $instance->id, 'start_date' => '2027-08-15', 'end_date' => '2028-06-15']);
        $instance->forceFill(['season_id' => $season->id, 'instance_date' => '2027-08-14'])->save();
        $service = app(SponsorshipService::class);
        $service->payForSeason($instance);
        $this->assertSame(0, $account->fresh()->balance);
        $instance->forceFill(['instance_date' => '2027-08-15'])->save();
        $service->payForSeason($instance);
        $service->payForSeason($instance);
        $this->assertSame($offer->annual_amount, $account->fresh()->balance);
        $this->assertDatabaseCount('sponsorship_payments', 1);
    }

    private function offer(): SponsorshipOffer
    {
        $contract = SponsorshipContract::factory()->create(['expires_at' => '2027-06-15']);
        $instance = Instance::query()->findOrFail($contract->instance_id);
        $season = Season::factory()->create(['instance_id' => $instance->id, 'start_date' => '2026-08-15', 'end_date' => '2027-06-15']);
        $contract->club->forceFill(['rank' => 10])->save();
        $instance->forceFill(['club_id' => $contract->club_id, 'season_id' => $season->id, 'instance_date' => '2026-12-15'])->save();
        app(SponsorshipOfferService::class)->process($instance);
        $this->actingAs($instance->user)->withHeaders(['instanceHash' => $instance->instance_hash]);

        return SponsorshipOffer::query()->where('club_id', $instance->club_id)->sole();
    }

    private function url(SponsorshipOffer $offer, string $action): string
    {
        return '/api/finance/sponsor-offers/'.$offer->id.'/'.$action;
    }
}
