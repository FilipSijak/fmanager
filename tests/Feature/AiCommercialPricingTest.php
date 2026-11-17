<?php

namespace Tests\Feature;

use App\Contracts\Search\PlayerSearchIndexDispatcher;
use App\Events\MonthlyUpdate;
use App\Models\Account;
use App\Models\BaseData\BaseCommercialCategory;
use App\Models\BaseData\BaseCommercialProducts;
use App\Models\Club;
use App\Models\ClubDemandProfile;
use App\Models\Competition;
use App\Models\Country;
use App\Models\Game;
use App\Models\Instance;
use App\Models\Season;
use App\Models\SeasonTicketSale;
use App\Models\Stadium;
use App\Models\StadiumCommercialProduct;
use App\Models\StadiumCommercialVenue;
use App\Services\CommercialService\AiCommercialPricingService;
use App\Services\SeasonTicketService\SeasonTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class AiCommercialPricingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function monthly_updates_adjust_ai_prices_and_preserve_user_prices_and_sold_passes(): void
    {
        [$instance, $userClub, $aiClub, $userProduct, $aiProduct] = $this->world();
        $userClub->stadium->forceFill(['ticket_price' => 73])->save();
        $userProduct->forceFill(['price_change_coef' => 1.37])->save();
        app(SeasonTicketService::class)->sellForSeason($instance);
        $sale = SeasonTicketSale::query()->forClub($aiClub)->sole();
        $account = $aiClub->account;
        $balance = $account->balance;
        $this->mock(PlayerSearchIndexDispatcher::class)->shouldReceive('reindexInstance')->once()->with($instance->id);

        event(new MonthlyUpdate($instance));

        $this->assertSame(110, $aiClub->stadium->ticket_price);
        $this->assertSame('1.1000', $aiProduct->fresh()->price_change_coef);
        $this->assertSame('2030-09-02', $aiClub->stadium->ai_prices_reviewed_on->toDateString());
        $this->assertSame(73, $userClub->stadium->fresh()->ticket_price);
        $this->assertSame('1.3700', $userProduct->fresh()->price_change_coef);
        $this->assertNull($userClub->stadium->fresh()->ai_prices_reviewed_on);
        $this->assertSame($sale->getAttributes(), $sale->fresh()->getAttributes());
        $this->assertSame($balance, $account->fresh()->balance);
        $this->assertSame(2, ClubDemandProfile::query()->count());
    }

    #[Test]
    public function repeated_monthly_updates_do_not_compound_price_changes_and_later_months_are_reviewed(): void
    {
        [$instance, , $aiClub, , $aiProduct] = $this->world();
        $pricing = app(AiCommercialPricingService::class);

        $pricing->adjustForMonth($instance);
        $pricing->adjustForMonth($instance);

        $this->assertSame(110, $aiClub->stadium->ticket_price);
        $this->assertSame('1.1000', $aiProduct->fresh()->price_change_coef);

        $instance->forceFill(['instance_date' => '2030-10-02'])->save();
        $pricing->adjustForMonth($instance);

        $this->assertSame('2030-10-02', $aiClub->stadium->fresh()->ai_prices_reviewed_on->toDateString());
        $this->assertSame(2, ClubDemandProfile::query()->forClub($aiClub)->count());

        $oldEvent = clone $instance;
        $oldEvent->forceFill(['instance_date' => '2030-09-02']);
        $pricing->adjustForMonth($oldEvent);

        $this->assertSame('2030-10-02', $aiClub->stadium->fresh()->ai_prices_reviewed_on->toDateString());
    }

    #[Test]
    public function taking_over_a_club_stops_ai_pricing_and_leaving_resumes_it_using_current_management(): void
    {
        [$instance, , $aiClub, , $aiProduct] = $this->world();
        $pricing = app(AiCommercialPricingService::class);
        $pricing->adjustForMonth($instance);
        $staleInstance = clone $instance;
        $staleInstance->forceFill(['instance_date' => '2030-10-02']);
        $instance->forceFill(['club_id' => $aiClub->id, 'instance_date' => '2030-10-02'])->save();
        $aiClub->stadium->forceFill(['ticket_price' => 37])->save();
        $aiProduct->forceFill(['price_change_coef' => 0.73])->save();

        $pricing->adjustForMonth($staleInstance);

        $this->assertSame(37, $aiClub->stadium->fresh()->ticket_price);
        $this->assertSame('0.7300', $aiProduct->fresh()->price_change_coef);
        $this->assertSame('2030-09-02', $aiClub->stadium->fresh()->ai_prices_reviewed_on->toDateString());

        $instance->forceFill(['club_id' => null, 'instance_date' => '2030-11-02'])->save();
        $pricing->adjustForMonth($instance);

        $this->assertSame(40, $aiClub->stadium->fresh()->ticket_price);
        $this->assertSame('0.8030', $aiProduct->fresh()->price_change_coef);
        $this->assertSame('2030-11-02', $aiClub->stadium->fresh()->ai_prices_reviewed_on->toDateString());
    }

    #[Test]
    public function an_ai_club_sharing_the_user_stadium_cannot_change_its_prices(): void
    {
        [$instance, $userClub, $aiClub, $userProduct] = $this->world();
        $aiClub->forceFill(['stadium_id' => $userClub->stadium_id])->save();
        $userClub->stadium->forceFill(['ticket_price' => 0])->save();
        $userProduct->forceFill(['price_change_coef' => 1.37])->save();

        app(AiCommercialPricingService::class)->adjustForMonth($instance);

        $this->assertSame(0, $userClub->stadium->fresh()->ticket_price);
        $this->assertSame('1.3700', $userProduct->fresh()->price_change_coef);
        $this->assertNull($userClub->stadium->fresh()->ai_prices_reviewed_on);
    }

    #[Test]
    public function pricing_is_scoped_to_the_event_instance_and_preserves_unavailable_products(): void
    {
        [$instance, , $aiClub, , $aiProduct] = $this->world();
        [, , $foreignClub, , $foreignProduct] = $this->world();
        $baseProduct = BaseCommercialProducts::query()->forceCreate(['slug' => 'disabled-water', 'name' => 'Water',
            'category_id' => $aiProduct->baseProduct->category_id, 'base_price' => 200]);
        $disabled = StadiumCommercialProduct::factory()->create(['instance_id' => $instance->id, 'stadium_id' => $aiClub->stadium_id,
            'base_product_id' => $baseProduct->id, 'price_change_coef' => 1.7, 'is_available' => false]);

        app(AiCommercialPricingService::class)->adjustForMonth($instance);

        $this->assertSame(110, $aiClub->stadium->ticket_price);
        $this->assertSame('1.1000', $aiProduct->fresh()->price_change_coef);
        $this->assertSame('1.7000', $disabled->fresh()->price_change_coef);
        $this->assertFalse($disabled->fresh()->is_available);
        $this->assertSame(100, $foreignClub->stadium->ticket_price);
        $this->assertSame('1.0000', $foreignProduct->fresh()->price_change_coef);
        $this->assertNull($foreignClub->stadium->ai_prices_reviewed_on);
    }

    #[Test]
    public function a_failed_price_review_rolls_back_product_changes_and_can_be_retried(): void
    {
        [$instance, , $aiClub, , $aiProduct] = $this->world();
        $eventName = 'eloquent.updating: '.Stadium::class;
        Event::listen($eventName, function (Stadium $stadium) use ($aiClub): void {
            if ($stadium->id === $aiClub->stadium_id && $stadium->isDirty('ai_prices_reviewed_on')) {
                throw new RuntimeException('Price review failed.');
            }
        });

        try {
            app(AiCommercialPricingService::class)->adjustForMonth($instance);
            $this->fail('Expected the price review to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Price review failed.', $exception->getMessage());
        } finally {
            Event::forget($eventName);
        }
        $this->assertSame(100, $aiClub->stadium->ticket_price);
        $this->assertSame('1.0000', $aiProduct->fresh()->price_change_coef);
        $this->assertNull($aiClub->stadium->ai_prices_reviewed_on);
        $this->assertSame(0, ClubDemandProfile::query()->forClub($aiClub)->count());

        app(AiCommercialPricingService::class)->adjustForMonth($instance);

        $this->assertSame(110, $aiClub->stadium->fresh()->ticket_price);
        $this->assertSame('1.1000', $aiProduct->fresh()->price_change_coef);
    }

    #[Test]
    public function fully_prepaid_capacity_preserves_ticket_prices_while_reviewing_product_prices(): void
    {
        [$instance, , $aiClub, , $aiProduct] = $this->world();
        app(SeasonTicketService::class)->sellForSeason($instance);
        $aiClub->stadium->forceFill(['active_capacity' => 400])->save();

        app(AiCommercialPricingService::class)->adjustForMonth($instance);

        $this->assertSame(100, $aiClub->stadium->fresh()->ticket_price);
        $this->assertSame('1.1000', $aiProduct->fresh()->price_change_coef);
        $this->assertSame(400, SeasonTicketSale::query()->forClub($aiClub)->sole()->quantity);
    }

    #[Test]
    public function upcoming_home_cup_games_influence_prices_while_cancelled_fixtures_and_away_games_do_not(): void
    {
        [$instance, $userClub, $aiClub] = $this->world();
        $cup = Competition::factory()->create(['instance_id' => $instance->id, 'rank' => 2000, 'type' => 'tournament', 'competition_scope' => 'domestic']);
        $homeGame = Game::query()->forInstance($instance->id)->where('hometeam_id', $aiClub->id)->sole();
        $homeGame->forceFill(['competition_id' => $cup->id])->save();
        $leagueId = Game::query()->forInstance($instance->id)->where('hometeam_id', $userClub->id)->value('competition_id');
        Game::factory()->count(5)->create(['instance_id' => $instance->id, 'season_id' => $instance->season_id, 'competition_id' => $leagueId,
            'hometeam_id' => $aiClub->id, 'awayteam_id' => $userClub->id, 'stadium_id' => $aiClub->stadium_id,
            'match_start' => '2030-09-03 15:00:00', 'status' => Game::STATUS_CANCELLED]);

        app(AiCommercialPricingService::class)->adjustForMonth($instance);

        $this->assertSame(90, $aiClub->stadium->ticket_price);
        $this->assertSame(0, SeasonTicketSale::query()->count());
    }

    #[Test]
    public function ai_clubs_sharing_a_stadium_only_adjust_its_prices_once_per_month(): void
    {
        [$instance, , $aiClub, , $aiProduct] = $this->world();
        Club::factory()->create(['instance_id' => $instance->id, 'stadium_id' => $aiClub->stadium_id, 'country_code' => 'GBR', 'rank' => 5]);

        app(AiCommercialPricingService::class)->adjustForMonth($instance);

        $this->assertSame(110, $aiClub->stadium->ticket_price);
        $this->assertSame('1.1000', $aiProduct->fresh()->price_change_coef);
    }

    /** @return array{Instance, Club, Club, StadiumCommercialProduct, StadiumCommercialProduct} */
    private function world(): array
    {
        if (! Country::query()->where('code', 'GBR')->exists()) {
            Country::query()->forceCreate(['code' => 'GBR', 'name' => 'United Kingdom', 'ranking' => 100, 'population' => 60000000]);
        }
        $instance = Instance::factory()->create(['instance_date' => '2030-09-02']);
        $season = Season::factory()->create(['instance_id' => $instance->id, 'start_date' => '2030-08-15', 'end_date' => '2031-06-15']);
        $instance->forceFill(['season_id' => $season->id])->save();
        $userStadium = Stadium::factory()->create(['instance_id' => $instance->id, 'country_code' => 'GBR', 'capacity' => 1000, 'active_capacity' => 1000, 'ticket_price' => 100]);
        $aiStadium = Stadium::factory()->create(['instance_id' => $instance->id, 'country_code' => 'GBR', 'capacity' => 1000, 'active_capacity' => 1000, 'ticket_price' => 100]);
        $userClub = Club::factory()->create(['instance_id' => $instance->id, 'stadium_id' => $userStadium->id, 'country_code' => 'GBR', 'rank' => 20]);
        $aiClub = Club::factory()->create(['instance_id' => $instance->id, 'stadium_id' => $aiStadium->id, 'country_code' => 'GBR', 'rank' => 20]);
        $instance->forceFill(['club_id' => $userClub->id])->save();
        Account::factory()->create(['club_id' => $userClub->id, 'balance' => 10000, 'future_balance' => 10000]);
        Account::factory()->create(['club_id' => $aiClub->id, 'balance' => 10000, 'future_balance' => 10000]);
        $competition = Competition::factory()->create(['instance_id' => $instance->id, 'rank' => 10000, 'type' => 'league', 'competition_scope' => 'domestic']);
        foreach ([$userClub, $aiClub] as $club) {
            DB::table('competition_season')->insert(['instance_id' => $instance->id, 'season_id' => $season->id, 'competition_id' => $competition->id, 'club_id' => $club->id]);
        }
        Game::factory()->create(['instance_id' => $instance->id, 'season_id' => $season->id, 'competition_id' => $competition->id,
            'hometeam_id' => $aiClub->id, 'awayteam_id' => $userClub->id, 'stadium_id' => $aiStadium->id, 'match_start' => '2030-09-15 15:00:00']);
        Game::factory()->create(['instance_id' => $instance->id, 'season_id' => $season->id, 'competition_id' => $competition->id,
            'hometeam_id' => $userClub->id, 'awayteam_id' => $aiClub->id, 'stadium_id' => $userStadium->id, 'match_start' => '2030-09-22 15:00:00']);
        $category = BaseCommercialCategory::query()->firstOrCreate(['slug' => 'bar'], ['name' => 'Bar']);
        $baseProduct = BaseCommercialProducts::query()->where('slug', 'lager')->first()
            ?? BaseCommercialProducts::query()->forceCreate(['slug' => 'lager', 'name' => 'Lager', 'category_id' => $category->id, 'base_price' => 500]);
        foreach ([$userStadium, $aiStadium] as $stadium) {
            StadiumCommercialVenue::factory()->create(['instance_id' => $instance->id, 'stadium_id' => $stadium->id, 'category_id' => $category->id, 'size' => 1]);
        }
        $userProduct = StadiumCommercialProduct::factory()->create(['instance_id' => $instance->id, 'stadium_id' => $userStadium->id, 'base_product_id' => $baseProduct->id, 'base_price' => 500]);
        $aiProduct = StadiumCommercialProduct::factory()->create(['instance_id' => $instance->id, 'stadium_id' => $aiStadium->id, 'base_product_id' => $baseProduct->id, 'base_price' => 500]);

        return [$instance, $userClub, $aiClub, $userProduct, $aiProduct];
    }
}
