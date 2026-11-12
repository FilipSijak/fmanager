<?php

namespace Tests\Feature;

use App\Events\PostMatch;
use App\Models\Account;
use App\Models\BaseData\BaseCommercialCategory;
use App\Models\BaseData\BaseCommercialProducts;
use App\Models\Club;
use App\Models\Competition;
use App\Models\Country;
use App\Models\FinanceTransactions;
use App\Models\Game;
use App\Models\Instance;
use App\Models\Season;
use App\Models\Stadium;
use App\Models\StadiumCommercialProduct;
use App\Models\StadiumCommercialVenue;
use App\Models\StadiumStand;
use App\Services\GameService\CompleteGameService;
use App\Services\MatchdayService\MatchdayService;
use App\StadiumStandPosition;
use App\StadiumStandStatus;
use App\Support\GameContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class MatchdayRevenueTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function completing_a_match_records_attendance_and_settles_home_gate_commercial_income_and_costs(): void
    {
        [$game, $homeAccount, $awayAccount, $stadium] = $this->match();
        $this->commercialVenue($stadium);
        $budget = $homeAccount->transfer_budget;

        $completed = app(CompleteGameService::class)->complete($game->id, 2, 1);

        $this->assertSame(1000, $completed->attendance);
        $this->assertSame(109200, $homeAccount->fresh()->balance);
        $this->assertSame(109200, $homeAccount->fresh()->future_balance);
        $this->assertSame($budget, $homeAccount->fresh()->transfer_budget);
        $this->assertSame(10000, $awayAccount->fresh()->balance);
        $settlement = $completed->matchdaySettlement()->sole();
        $this->assertSame(100, $settlement->ticket_price);
        $this->assertSame(100000, $settlement->gate_receipts);
        $this->assertSame(1800, $settlement->commercial_income);
        $this->assertSame(2600, $settlement->operating_costs);
        $this->assertSame('2030-01-07', $settlement->settled_on->toDateString());
        $this->assertDatabaseCount('finance_transactions', 3);
        $this->assertDatabaseHas('finance_transactions', ['reference' => 'matchday:'.$game->id.':gate_receipts', 'amount' => 100000, 'transaction_date' => '2030-01-07 00:00:00']);
        $this->assertDatabaseHas('finance_transactions', ['reference' => 'matchday:'.$game->id.':commercial_income', 'amount' => 1800]);
        $this->assertDatabaseHas('finance_transactions', ['reference' => 'matchday:'.$game->id.':operating_costs', 'amount' => 2600, 'sending_account_id' => $homeAccount->id]);
    }

    #[Test]
    public function repeating_completion_and_post_match_events_does_not_recalculate_or_pay_twice(): void
    {
        [$game, $account, , $stadium] = $this->match();
        $product = $this->commercialVenue($stadium);
        app(CompleteGameService::class)->complete($game->id, 2, 1);
        $product->forceFill(['price_change_coef' => 10, 'is_available' => false])->save();
        $stadium->forceFill(['active_capacity' => 500])->save();
        app(CompleteGameService::class)->complete($game->id, 9, 0);
        app(GameContext::class)->set(9999, 9999, '2040-01-01');

        event(new PostMatch($game->fresh()));
        event(new PostMatch($game->fresh()));

        $this->assertSame(109200, $account->fresh()->balance);
        $this->assertSame(1000, $game->fresh()->attendance);
        $this->assertDatabaseCount('matchday_settlements', 1);
        $this->assertDatabaseCount('finance_transactions', 3);
    }

    #[Test]
    #[DataProvider('unfinishedStatuses')]
    public function unfinished_cancelled_and_abandoned_games_do_not_settle(string $status): void
    {
        [$game, $account] = $this->match();
        $game->forceFill(['status' => $status])->save();

        $this->assertNull(app(MatchdayService::class)->settle($game));
        $this->assertSame(10000, $account->fresh()->balance);
        $this->assertNull($game->fresh()->attendance);
        $this->assertDatabaseCount('finance_transactions', 0);
        $this->assertDatabaseCount('matchday_settlements', 0);
    }

    /** @return array<string, array{string}> */
    public static function unfinishedStatuses(): array
    {
        return ['scheduled' => ['scheduled'], 'playing' => ['in_progress'], 'postponed' => ['postponed'], 'cancelled' => ['cancelled'], 'abandoned' => ['abandoned']];
    }

    #[Test]
    public function unavailable_stands_are_excluded_even_when_cached_capacity_is_stale(): void
    {
        [$game, $account, , $stadium] = $this->match();
        StadiumStand::factory()->create(['stadium_id' => $stadium->id, 'position' => StadiumStandPosition::NORTH, 'capacity' => 200, 'status' => StadiumStandStatus::ACTIVE]);
        StadiumStand::factory()->create(['stadium_id' => $stadium->id, 'position' => StadiumStandPosition::SOUTH, 'capacity' => 800, 'status' => StadiumStandStatus::UNDER_CONSTRUCTION]);
        $game->forceFill(['status' => Game::STATUS_COMPLETED])->save();

        $settlement = app(MatchdayService::class)->settle($game);

        $this->assertSame(200, $settlement->attendance);
        $this->assertSame(20000, $settlement->gate_receipts);
        $this->assertSame(29500, $account->fresh()->balance);
    }

    #[Test]
    public function a_closed_stadium_has_no_receipts_but_operating_costs_can_overdraw_cash(): void
    {
        [$game, $account, , $stadium] = $this->match();
        $stadium->forceFill(['active_capacity' => 0])->save();
        $account->forceFill(['balance' => 0, 'future_balance' => 0])->save();
        $game->forceFill(['status' => Game::STATUS_COMPLETED])->save();

        $settlement = app(MatchdayService::class)->settle($game);

        $this->assertSame(0, $settlement->attendance);
        $this->assertSame(0, $settlement->gate_receipts);
        $this->assertSame(0, $settlement->commercial_income);
        $this->assertSame(100, $settlement->operating_costs);
        $this->assertSame(-100, $account->fresh()->balance);
        $this->assertDatabaseCount('finance_transactions', 1);
    }

    #[Test]
    public function unavailable_products_unbuilt_venues_and_other_stadiums_cannot_earn_match_income(): void
    {
        [$game, $account, , $stadium] = $this->match();
        $product = $this->commercialVenue($stadium);
        $product->forceFill(['is_available' => false])->save();
        $shop = BaseCommercialCategory::query()->create(['slug' => 'shop', 'name' => 'Shop']);
        $baseProduct = BaseCommercialProducts::query()->forceCreate(['slug' => 'scarf', 'name' => 'Scarf', 'category_id' => $shop->id, 'base_price' => 1000]);
        StadiumCommercialProduct::factory()->create(['stadium_id' => $stadium->id, 'instance_id' => $stadium->instance_id, 'base_product_id' => $baseProduct->id, 'base_price' => 1000]);
        $otherStadium = Stadium::factory()->create(['instance_id' => $stadium->instance_id]);
        $this->commercialVenue($otherStadium);
        $game->forceFill(['status' => Game::STATUS_COMPLETED])->save();

        $settlement = app(MatchdayService::class)->settle($game);

        $this->assertSame(0, $settlement->commercial_income);
        $this->assertSame(2100, $settlement->operating_costs);
        $this->assertSame(107900, $account->fresh()->balance);
    }

    #[Test]
    public function a_failed_expense_rolls_back_match_completion_attendance_income_and_the_settlement_marker(): void
    {
        [$game, $account] = $this->match();
        $eventName = 'eloquent.creating: '.FinanceTransactions::class;
        Event::listen($eventName, function (FinanceTransactions $transaction): void {
            if ($transaction->category === 'matchday_operating_costs') {
                throw new RuntimeException('Operating cost failed.');
            }
        });
        try {
            app(CompleteGameService::class)->complete($game->id, 1, 0);
            $this->fail('Expected match settlement to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Operating cost failed.', $exception->getMessage());
        } finally {
            Event::forget($eventName);
        }

        $this->assertSame(Game::STATUS_SCHEDULED, $game->fresh()->status);
        $this->assertNull($game->fresh()->attendance);
        $this->assertNull($game->fresh()->processed_at);
        $this->assertSame(10000, $account->fresh()->balance);
        $this->assertDatabaseCount('finance_transactions', 0);
        $this->assertDatabaseCount('matchday_settlements', 0);
        $this->assertDatabaseHas('competition_season', ['club_id' => $game->hometeam_id, 'played' => 0]);

        app(CompleteGameService::class)->complete($game->id, 1, 0);

        $this->assertSame(107900, $account->fresh()->balance);
        $this->assertDatabaseCount('matchday_settlements', 1);
    }

    #[Test]
    public function a_stadium_in_another_game_cannot_be_used_for_settlement(): void
    {
        [$game, $account] = $this->match();
        $foreignStadium = Stadium::factory()->create();
        $game->forceFill(['stadium_id' => $foreignStadium->id, 'status' => Game::STATUS_COMPLETED])->save();

        try {
            app(MatchdayService::class)->settle($game);
            $this->fail('Expected cross-game stadium access to fail.');
        } catch (ModelNotFoundException) {
            $this->assertSame(10000, $account->fresh()->balance);
            $this->assertDatabaseCount('matchday_settlements', 0);
            $this->assertDatabaseCount('finance_transactions', 0);
        }
    }

    #[Test]
    public function stored_stadium_prices_are_used_and_completed_match_snapshots_do_not_change(): void
    {
        [$game, , , $stadium] = $this->match();
        $stadium->forceFill(['ticket_price' => 37])->save();
        $game->forceFill(['status' => Game::STATUS_COMPLETED])->save();
        $first = app(MatchdayService::class)->settle($game);
        $stadium->forceFill(['ticket_price' => 80])->save();

        $repeated = app(MatchdayService::class)->settle($game);

        $this->assertSame(37, $first->ticket_price);
        $this->assertSame(37000, $first->gate_receipts);
        $this->assertSame(37, $repeated->ticket_price);
        $this->assertSame(80, $stadium->fresh()->ticket_price);
    }

    #[Test]
    public function a_stored_zero_ticket_price_gives_free_admission_without_creating_gate_receipts(): void
    {
        [$game, , , $stadium] = $this->match();
        $stadium->forceFill(['ticket_price' => 0])->save();
        $game->forceFill(['status' => Game::STATUS_COMPLETED])->save();

        $settlement = app(MatchdayService::class)->settle($game);

        $this->assertSame(1000, $settlement->attendance);
        $this->assertSame(0, $settlement->ticket_price);
        $this->assertSame(0, $settlement->gate_receipts);
        $this->assertDatabaseCount('finance_transactions', 1);
    }

    /** @return array{Game, Account, Account, Stadium} */
    private function match(): array
    {
        $instance = Instance::factory()->create(['instance_date' => '2030-01-07']);
        $season = Season::factory()->create(['instance_id' => $instance->id, 'start_date' => '2029-08-01', 'end_date' => '2030-06-30']);
        $instance->forceFill(['season_id' => $season->id])->save();
        $competition = Competition::factory()->create(['instance_id' => $instance->id, 'rank' => 10000, 'type' => 'league', 'groups' => null, 'clubs_number' => 2]);
        Country::query()->forceCreate(['code' => 'GBR', 'name' => 'United Kingdom', 'ranking' => 100, 'population' => 60000000]);
        $stadium = Stadium::factory()->create(['instance_id' => $instance->id, 'capacity' => 1000, 'active_capacity' => 1000, 'ticket_price' => 100]);
        $home = Club::factory()->create(['instance_id' => $instance->id, 'stadium_id' => $stadium->id, 'rank' => 20, 'country_code' => 'GBR']);
        $away = Club::factory()->create(['instance_id' => $instance->id, 'rank' => 20]);
        foreach ([$home, $away] as $club) {
            DB::table('competition_season')->insert(['instance_id' => $instance->id, 'competition_id' => $competition->id, 'season_id' => $season->id, 'club_id' => $club->id]);
        }
        $homeAccount = Account::factory()->create(['club_id' => $home->id, 'balance' => 10000, 'future_balance' => 10000]);
        $awayAccount = Account::factory()->create(['club_id' => $away->id, 'balance' => 10000, 'future_balance' => 10000]);
        $game = Game::factory()->create(['instance_id' => $instance->id, 'season_id' => $season->id, 'competition_id' => $competition->id,
            'hometeam_id' => $home->id, 'awayteam_id' => $away->id, 'stadium_id' => $stadium->id, 'attendance' => null, 'match_start' => '2030-01-07 15:00:00']);

        return [$game, $homeAccount, $awayAccount, $stadium];
    }

    private function commercialVenue(Stadium $stadium): StadiumCommercialProduct
    {
        $category = BaseCommercialCategory::query()->firstOrCreate(['slug' => 'bar'], ['name' => 'Bar']);
        $baseProduct = BaseCommercialProducts::query()->where('slug', 'lager')->first()
            ?? BaseCommercialProducts::query()->forceCreate(['slug' => 'lager', 'name' => 'Lager', 'category_id' => $category->id, 'base_price' => 450]);
        StadiumCommercialVenue::factory()->create(['instance_id' => $stadium->instance_id, 'stadium_id' => $stadium->id, 'category_id' => $category->id, 'size' => 1]);

        return StadiumCommercialProduct::factory()->create(['instance_id' => $stadium->instance_id, 'stadium_id' => $stadium->id, 'base_product_id' => $baseProduct->id, 'base_price' => 450, 'price_change_coef' => 2]);
    }
}
