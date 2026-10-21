<?php

namespace Tests\Feature;

use App\Events\NextDay;
use App\GameEntityType;
use App\Models\Club;
use App\Models\GameEntity;
use App\Models\GameEntityAccount;
use App\Models\Instance;
use App\Models\Season;
use App\Models\SponsorshipContract;
use App\Models\SponsorshipOffer;
use App\Services\FinanceService\SponsorshipOfferService;
use App\Services\FinanceService\SponsorshipOfferStatus;
use App\Support\GameContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SponsorshipOfferTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function approaches_begin_exactly_six_months_before_expiry_and_only_one_can_be_pending(): void
    {
        $instance = $this->world('2026-12-14');
        $service = app(SponsorshipOfferService::class);
        $service->process($instance);
        $this->assertDatabaseCount('sponsorship_offers', 0);

        $instance->forceFill(['instance_date' => '2026-12-15'])->save();
        event(new NextDay($instance));
        $instance->forceFill(['instance_date' => '2026-12-16'])->save();
        $service->process($instance);

        $offer = SponsorshipOffer::query()->sole();
        $this->assertSame(SponsorshipOfferStatus::Pending, $offer->status);
        $this->assertSame('2026-12-15', $offer->offered_on->toDateString());
        $this->assertSame('2027-08-15', $offer->starts_at->toDateString());
        $this->assertContains($offer->expires_at->toDateString(), ['2028-06-15', '2029-06-15', '2030-06-15', '2031-06-15', '2032-06-15']);
        $this->assertSame(3750000, $offer->annual_amount);
        $this->assertDatabaseHas('news', ['club_id' => $instance->club_id, 'type' => 'sponsorship']);
    }

    #[Test]
    public function sponsors_without_contracts_take_priority_and_foreign_sponsors_are_excluded(): void
    {
        $instance = $this->world();
        $freeEntity = GameEntity::factory()->create(['instance_id' => $instance->id, 'type' => GameEntityType::SPONSOR, 'rank' => 7]);
        $free = GameEntityAccount::factory()->create(['instance_id' => $instance->id, 'game_entity_id' => $freeEntity->id]);
        SponsorshipContract::factory()->create();

        app(SponsorshipOfferService::class)->process($instance);

        $this->assertSame($free->id, SponsorshipOffer::query()->sole()->sponsor_account_id);
        $this->assertSame(1920000, SponsorshipOffer::query()->sole()->annual_amount);
    }

    #[Test]
    #[DataProvider('sponsorRanks')]
    public function approaches_require_eligible_ranks_and_use_the_combined_offer_rank(int $clubRank, int $sponsorRank, ?int $amount): void
    {
        $instance = $this->world(clubRank: $clubRank, sponsorRank: $sponsorRank);

        app(SponsorshipOfferService::class)->process($instance);

        if ($amount === null) {
            $this->assertDatabaseCount('sponsorship_offers', 0);
        } else {
            $this->assertSame($amount, SponsorshipOffer::query()->sole()->annual_amount);
            $this->assertSame(24057000, SponsorshipOffer::query()->sole()->maximum_annual_amount);
        }
    }

    /** @return array<string, array{int, int, ?int}> */
    public static function sponsorRanks(): array
    {
        return [
            'club three ranks higher' => [20, 17, 21870000],
            'sponsor three ranks higher' => [17, 20, 21870000],
            'club four ranks higher' => [10, 6, null],
            'sponsor four ranks higher' => [10, 14, null],
        ];
    }

    #[Test]
    public function an_unassigned_sponsor_outside_the_rank_range_cannot_take_priority(): void
    {
        $instance = $this->world();
        $current = SponsorshipContract::query()->sole();
        $entity = GameEntity::factory()->create(['instance_id' => $instance->id, 'type' => GameEntityType::SPONSOR, 'rank' => 6]);
        GameEntityAccount::factory()->create(['instance_id' => $instance->id, 'game_entity_id' => $entity->id]);

        app(SponsorshipOfferService::class)->process($instance);

        $this->assertSame($current->sponsor_account_id, SponsorshipOffer::query()->sole()->sponsor_account_id);
    }

    #[Test]
    public function month_end_expirations_use_the_exact_six_month_boundary(): void
    {
        $instance = $this->world('2027-02-27');
        SponsorshipContract::query()->sole()->update(['expires_at' => '2027-08-31']);
        $service = app(SponsorshipOfferService::class);
        $service->process($instance);
        $this->assertDatabaseCount('sponsorship_offers', 0);
        $instance->forceFill(['instance_date' => '2027-02-28'])->save();
        $service->process($instance);
        $this->assertDatabaseCount('sponsorship_offers', 1);
        $this->assertSame('2028-08-15', SponsorshipOffer::query()->sole()->starts_at->toDateString());
    }

    #[Test]
    public function a_sponsor_can_make_only_three_approaches_per_club_per_season(): void
    {
        $instance = $this->world();
        $service = app(SponsorshipOfferService::class);
        foreach ([15, 16, 17] as $attempt => $day) {
            $date = '2026-12-'.$day;
            $instance->forceFill(['instance_date' => $date])->save();
            app(GameContext::class)->set($instance->id, $instance->season_id, $date);
            $service->process($instance);
            $offer = SponsorshipOffer::query()->where('status', SponsorshipOfferStatus::Pending)->sole();
            $this->assertSame($attempt + 1, $offer->attempt_number);
            $service->respond($offer->id, 'reject');
            $service->process($instance);
            $this->assertSame($attempt + 1, SponsorshipOffer::query()->count());
        }
        $instance->forceFill(['instance_date' => '2026-12-18'])->save();
        $service->process($instance);
        $this->assertDatabaseCount('sponsorship_offers', 3);

        $season = Season::factory()->create(['instance_id' => $instance->id, 'start_date' => '2027-08-15', 'end_date' => '2028-06-15']);
        $instance->forceFill(['season_id' => $season->id, 'instance_date' => '2027-06-16'])->save();
        $service->process($instance);
        $this->assertDatabaseHas('sponsorship_offers', ['season_id' => $season->id, 'attempt_number' => 1, 'status' => 'pending']);
    }

    #[Test]
    public function unmanaged_clubs_sign_future_contracts_automatically_without_duplicate_renewals(): void
    {
        $instance = $this->world();
        $club = Club::factory()->create(['instance_id' => $instance->id, 'rank' => 10]);
        $current = SponsorshipContract::query()->sole();
        SponsorshipContract::factory()->create(['instance_id' => $instance->id, 'club_id' => $club->id, 'sponsor_account_id' => $current->sponsor_account_id, 'expires_at' => '2027-06-15']);
        $service = app(SponsorshipOfferService::class);
        $service->process($instance);
        $instance->forceFill(['instance_date' => '2026-12-16'])->save();
        $service->process($instance);

        $offer = SponsorshipOffer::query()->where('club_id', $club->id)->sole();
        $this->assertSame(SponsorshipOfferStatus::Accepted, $offer->status);
        $this->assertSame('2027-08-15', $offer->acceptedContract->starts_at->toDateString());
        $this->assertSame(2, SponsorshipContract::query()->where('club_id', $club->id)->count());
    }

    #[Test]
    public function expired_offers_release_the_active_slot(): void
    {
        $instance = $this->world();
        $service = app(SponsorshipOfferService::class);
        $service->process($instance);
        $old = SponsorshipOffer::query()->sole();
        $instance->forceFill(['instance_date' => '2027-08-15'])->save();
        $service->process($instance);
        $this->assertSame(SponsorshipOfferStatus::Expired, $old->fresh()->status);
        $new = SponsorshipOffer::query()->where('status', SponsorshipOfferStatus::Pending)->sole();
        $this->assertSame('2028-08-15', $new->starts_at->toDateString());
    }

    #[Test]
    public function the_database_prevents_two_pending_offers_even_from_different_sponsors(): void
    {
        $instance = $this->world();
        app(SponsorshipOfferService::class)->process($instance);
        $entity = GameEntity::factory()->create(['instance_id' => $instance->id, 'type' => GameEntityType::SPONSOR]);
        $sponsor = GameEntityAccount::factory()->create(['instance_id' => $instance->id, 'game_entity_id' => $entity->id]);
        $this->expectException(QueryException::class);
        SponsorshipOffer::factory()->create(['instance_id' => $instance->id, 'club_id' => $instance->club_id, 'season_id' => $instance->season_id, 'sponsor_account_id' => $sponsor->id, 'attempt_number' => 1]);
    }

    private function world(string $date = '2026-12-15', int $clubRank = 10, int $sponsorRank = 10): Instance
    {
        $contract = SponsorshipContract::factory()->create(['starts_at' => '2026-08-15', 'expires_at' => '2027-06-15']);
        $instance = Instance::query()->findOrFail($contract->instance_id);
        $season = Season::factory()->create(['instance_id' => $instance->id, 'start_date' => '2026-08-15', 'end_date' => '2027-06-15']);
        $contract->club->forceFill(['rank' => $clubRank])->save();
        $contract->sponsorAccount->gameEntity->update(['rank' => $sponsorRank]);
        $instance->forceFill(['club_id' => $contract->club_id, 'season_id' => $season->id, 'instance_date' => $date])->save();
        app(GameContext::class)->set($instance->id, $season->id, $date);

        return $instance;
    }
}
