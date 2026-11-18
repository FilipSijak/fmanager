<?php

namespace Tests\Feature;

use App\Events\PostMatch;
use App\Events\SeasonStarted;
use App\Listeners\SellSeasonTickets;
use App\Models\Account;
use App\Models\Club;
use App\Models\Competition;
use App\Models\Country;
use App\Models\FinanceTransactions;
use App\Models\Game;
use App\Models\Instance;
use App\Models\Season;
use App\Models\SeasonTicketSale;
use App\Models\Stadium;
use App\Models\StadiumStand;
use App\Models\StadiumStandConstruction;
use App\Services\CommercialService\Domain\TicketType;
use App\Services\CommercialService\SeasonTicketService;
use App\Services\GameService\CompleteGameService;
use App\Services\MatchdayService\MatchdayService;
use App\Services\StadiumService\StadiumService;
use App\StadiumStandPosition;
use App\StadiumStandStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class SeasonTicketsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function presales_sell_forty_percent_and_price_only_home_league_games_once_per_season(): void
    {
        [$instance, $home, $away, $league, $cup, $continental, $account] = $this->world();
        $this->game($instance, $home, $away, $league, '2030-08-15');
        $this->game($instance, $home, $away, $league, '2030-08-22');
        $this->game($instance, $away, $home, $league, '2030-08-29');
        $this->game($instance, $home, $away, $cup, '2030-09-01');
        $this->game($instance, $home, $away, $continental, '2030-09-08');
        $service = app(SeasonTicketService::class);

        $service->sellForSeason($instance);
        $service->sellForSeason($instance);

        $sale = SeasonTicketSale::query()->forClub($home)->sole();
        $this->assertSame(400, $sale->quantity);
        $this->assertSame(1000, $sale->capacity);
        $this->assertSame(2, $sale->home_games);
        $this->assertSame(200, $sale->ticket_price);
        $this->assertSame(80000, $sale->amount);
        $this->assertSame('2030-07-01', $sale->sold_on->toDateString());
        $this->assertSame(90000, $account->fresh()->balance);
        $this->assertSame(90000, $account->fresh()->future_balance);
        $this->assertSame(2, SeasonTicketSale::query()->count());
        $this->assertDatabaseCount('finance_transactions', 2);
        $this->assertDatabaseHas('finance_transactions', ['id' => $sale->transaction_id, 'category' => 'season_ticket_sales', 'amount' => 80000, 'transaction_date' => '2030-07-01 00:00:00']);
    }

    #[Test]
    public function holders_enter_league_matches_without_paying_again_but_pay_full_cup_and_continental_prices(): void
    {
        [$instance, $home, $away, $league, $cup, $continental, $account] = $this->world();
        $leagueGame = $this->game($instance, $home, $away, $league, '2030-08-15');
        $cupGame = $this->game($instance, $home, $away, $cup, '2030-08-22');
        $continentalGame = $this->game($instance, $home, $away, $continental, '2030-08-29');
        app(SeasonTicketService::class)->sellForSeason($instance);
        $settlements = [];
        foreach ([$leagueGame, $cupGame, $continentalGame] as $game) {
            $game->forceFill(['status' => Game::STATUS_COMPLETED])->save();
            $settlements[] = app(MatchdayService::class)->settle($game);
        }

        $this->assertSame(TicketType::LEAGUE, $settlements[0]->ticket_type);
        $this->assertSame(400, $settlements[0]->season_ticket_attendance);
        $this->assertSame(600, $settlements[0]->paying_attendance);
        $this->assertSame(60000, $settlements[0]->gate_receipts);
        $this->assertSame(TicketType::CUP, $settlements[1]->ticket_type);
        $this->assertSame(80, $settlements[1]->ticket_price);
        $this->assertSame(0, $settlements[1]->season_ticket_attendance);
        $this->assertSame(80000, $settlements[1]->gate_receipts);
        $this->assertSame(TicketType::CONTINENTAL, $settlements[2]->ticket_type);
        $this->assertSame(120, $settlements[2]->ticket_price);
        $this->assertSame(0, $settlements[2]->season_ticket_attendance);
        $this->assertSame(120000, $settlements[2]->gate_receipts);
        $this->assertSame(303700, $account->fresh()->balance);
        $this->assertDatabaseCount('season_ticket_sales', 1);
    }

    #[Test]
    public function midseason_sales_exclude_played_cancelled_and_past_fixtures(): void
    {
        [$instance, $home, $away, $league, $cup] = $this->world();
        $instance->forceFill(['instance_date' => '2030-09-01'])->save();
        $this->game($instance, $home, $away, $league, '2030-08-15');
        $played = $this->game($instance, $home, $away, $league, '2030-09-02');
        $played->forceFill(['status' => Game::STATUS_COMPLETED])->save();
        $cancelled = $this->game($instance, $home, $away, $league, '2030-10-01');
        $cancelled->forceFill(['status' => Game::STATUS_CANCELLED])->save();
        $this->game($instance, $home, $away, $league, '2030-10-08');
        $this->game($instance, $home, $away, $cup, '2030-10-15');

        app(SeasonTicketService::class)->sellForSeason($instance);

        $sale = SeasonTicketSale::query()->forClub($home)->sole();
        $this->assertSame(1, $sale->home_games);
        $this->assertSame(100, $sale->ticket_price);
        $this->assertSame(40000, $sale->amount);
    }

    #[Test]
    public function allocation_rounds_down_and_is_not_resold_when_capacity_or_prices_change(): void
    {
        [$instance, $home, $away, $league] = $this->world();
        $stadium = Stadium::query()->findOrFail($home->stadium_id);
        $stadium->forceFill(['capacity' => 1234, 'active_capacity' => 1234])->save();
        $this->game($instance, $home, $away, $league, '2030-08-15');
        app(SeasonTicketService::class)->sellForSeason($instance);
        $stadium->forceFill(['capacity' => 2000, 'active_capacity' => 2000, 'ticket_price' => 200])->save();

        app(SeasonTicketService::class)->sellForSeason($instance);

        $sale = SeasonTicketSale::query()->forClub($home)->sole();
        $this->assertSame(493, $sale->quantity);
        $this->assertSame(100, $sale->league_ticket_price);
        $this->assertSame(49300, $sale->amount);
        $this->assertDatabaseCount('finance_transactions', 1);
    }

    #[Test]
    public function every_new_season_gets_its_own_sale_and_current_ticket_price(): void
    {
        [$instance, $home, $away, $league] = $this->world();
        $this->game($instance, $home, $away, $league, '2030-08-15');
        app(SeasonTicketService::class)->sellForSeason($instance);
        $nextSeason = Season::factory()->create(['instance_id' => $instance->id, 'start_date' => '2031-08-15', 'end_date' => '2032-06-15']);
        $instance->forceFill(['season_id' => $nextSeason->id, 'instance_date' => '2031-07-01'])->save();
        Stadium::query()->whereKey($home->stadium_id)->update(['ticket_price' => 200]);
        $this->game($instance, $home, $away, $league, '2031-08-15');

        app(SeasonTicketService::class)->sellForSeason($instance);

        $this->assertSame(2, SeasonTicketSale::query()->forClub($home)->count());
        $this->assertDatabaseHas('season_ticket_sales', ['club_id' => $home->id, 'season_id' => $nextSeason->id, 'ticket_price' => 200, 'quantity' => 72, 'amount' => 14400]);
    }

    #[Test]
    public function failed_sale_persistence_rolls_back_income_and_can_be_retried(): void
    {
        [$instance, $home, $away, $league, , , $account] = $this->world();
        $this->game($instance, $home, $away, $league, '2030-08-15');
        $eventName = 'eloquent.creating: '.SeasonTicketSale::class;
        Event::listen($eventName, function (): void {
            throw new RuntimeException('Sale persistence failed.');
        });
        try {
            app(SeasonTicketService::class)->sellForSeason($instance);
            $this->fail('Expected sale persistence to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Sale persistence failed.', $exception->getMessage());
        } finally {
            Event::forget($eventName);
        }
        $this->assertSame(10000, $account->fresh()->balance);
        $this->assertDatabaseCount('finance_transactions', 0);
        $this->assertDatabaseCount('season_ticket_sales', 0);

        app(SeasonTicketService::class)->sellForSeason($instance);

        $this->assertSame(50000, $account->fresh()->balance);
        $this->assertDatabaseCount('season_ticket_sales', 1);
    }

    #[Test]
    public function clubs_without_upcoming_home_league_games_do_not_sell_passes(): void
    {
        [$instance, $home, $away, , $cup, $continental, $account] = $this->world();
        $this->game($instance, $home, $away, $cup, '2030-08-15');
        $this->game($instance, $home, $away, $continental, '2030-08-22');

        app(SeasonTicketService::class)->sellForSeason($instance);

        $this->assertSame(10000, $account->fresh()->balance);
        $this->assertDatabaseCount('season_ticket_sales', 0);
        $this->assertDatabaseCount('finance_transactions', 0);
    }

    #[Test]
    public function the_season_started_listener_sells_passes_for_the_event_instance_only(): void
    {
        [$instance, $home, $away, $league] = $this->world();
        $this->game($instance, $home, $away, $league, '2030-08-15');
        [$foreignInstance, $foreignHome, $foreignAway, $foreignLeague] = $this->world();
        $this->game($foreignInstance, $foreignHome, $foreignAway, $foreignLeague, '2030-08-15');
        Event::fake([SeasonStarted::class]);
        Event::assertListening(SeasonStarted::class, SellSeasonTickets::class);

        app(SellSeasonTickets::class)->handle(new SeasonStarted($instance));

        $this->assertDatabaseCount('season_ticket_sales', 1);
        $this->assertDatabaseHas('season_ticket_sales', ['instance_id' => $instance->id, 'club_id' => $home->id]);
        $this->assertDatabaseMissing('season_ticket_sales', ['instance_id' => $foreignInstance->id]);
    }

    #[Test]
    #[DataProvider('constructionCapacities')]
    public function construction_removes_regular_seats_first_and_refunds_only_displaced_holders_until_reopening(int $capacity, int $refundQuantity, int $refundAmount, int $finalBalance): void
    {
        [$instance, $home, $away, $league, , , $account] = $this->world();
        $before = $this->game($instance, $home, $away, $league, '2030-08-15');
        $during = $this->game($instance, $home, $away, $league, '2030-08-22');
        $after = $this->game($instance, $home, $away, $league, '2030-08-29');
        app(SeasonTicketService::class)->sellForSeason($instance);
        $this->startStandExpansion($home, $capacity);
        $budget = $account->transfer_budget;
        $service = app(MatchdayService::class);

        $before->forceFill(['status' => Game::STATUS_COMPLETED])->save();
        $beforeSettlement = $service->settle($before);
        $during->forceFill(['status' => Game::STATUS_COMPLETED])->save();
        $duringSettlement = $service->settle($during);
        app(StadiumService::class)->completeStandConstructionForInstance($instance, CarbonImmutable::parse('2030-08-27'));
        $after->forceFill(['status' => Game::STATUS_COMPLETED])->save();
        $afterSettlement = $service->settle($after);
        event(new PostMatch($during->fresh()));

        $this->assertSame(1000, $beforeSettlement->attendance);
        $this->assertSame(0, $beforeSettlement->season_ticket_refund_amount);
        $this->assertSame($capacity, $duringSettlement->attendance);
        $this->assertSame(400 - $refundQuantity, $duringSettlement->season_ticket_attendance);
        $this->assertSame($refundQuantity, $duringSettlement->season_ticket_refund_quantity);
        $this->assertSame($refundAmount, $duringSettlement->season_ticket_refund_amount);
        $this->assertSame(400, $afterSettlement->season_ticket_attendance);
        $this->assertSame(0, $afterSettlement->season_ticket_refund_amount);
        $this->assertSame(2000, $afterSettlement->attendance);
        $this->assertSame($finalBalance, $account->fresh()->balance);
        $this->assertSame($finalBalance, $account->fresh()->future_balance);
        $this->assertSame($budget, $account->fresh()->transfer_budget);
        $this->assertSame($refundAmount, (int) FinanceTransactions::query()->where('category', 'season_ticket_refunds')->sum('amount'));
        $this->assertSame($refundAmount > 0 ? 1 : 0, FinanceTransactions::query()->where('category', 'season_ticket_refunds')->count());
        $this->assertSame(400, SeasonTicketSale::query()->forClub($home)->sole()->quantity);
        $this->assertSame(120000, SeasonTicketSale::query()->forClub($home)->sole()->amount);
    }

    /** @return array<string, array{int, int, int, int}> */
    public static function constructionCapacities(): array
    {
        return [
            'regular seats lost first' => [700, 0, 0, 372300],
            'exactly enough seats for holders' => [400, 0, 0, 342900],
            'half the holders displaced' => [200, 200, 20000, 323300],
            'all holders displaced' => [0, 400, 40000, 303700],
        ];
    }

    #[Test]
    public function cancelled_league_fixtures_and_cup_matches_do_not_refund_the_annual_pass(): void
    {
        [$instance, $home, $away, $league, $cup, $continental, $account] = $this->world();
        $cancelled = $this->game($instance, $home, $away, $league, '2030-08-22');
        $cupGame = $this->game($instance, $home, $away, $cup, '2030-08-23');
        $continentalGame = $this->game($instance, $home, $away, $continental, '2030-08-24');
        app(SeasonTicketService::class)->sellForSeason($instance);
        $sale = SeasonTicketSale::query()->forClub($home)->sole();
        $this->startStandExpansion($home, 0);
        $balance = $account->fresh()->balance;

        app(CompleteGameService::class)->cancel($cancelled->id);
        $service = app(MatchdayService::class);
        $cancelledSettlement = $service->settle($cancelled);
        $balanceAfterCancellation = $account->fresh()->balance;
        $cupGame->forceFill(['status' => Game::STATUS_COMPLETED])->save();
        $cupSettlement = $service->settle($cupGame);
        $continentalGame->forceFill(['status' => Game::STATUS_COMPLETED])->save();
        $continentalSettlement = $service->settle($continentalGame);

        $this->assertNull($cancelledSettlement);
        $this->assertSame($balance, $balanceAfterCancellation);
        $this->assertSame(0, $cupSettlement->season_ticket_refund_amount);
        $this->assertSame(0, $continentalSettlement->season_ticket_refund_amount);
        $this->assertSame($sale->getAttributes(), $sale->fresh()->getAttributes());
        $this->assertDatabaseMissing('finance_transactions', ['category' => 'season_ticket_refunds']);
    }

    #[Test]
    public function delayed_settlement_refunds_the_original_pass_rate_for_the_match_date_and_can_retry_failed_payments(): void
    {
        [$instance, $home, $away, $league, , , $account] = $this->world();
        $game = $this->game($instance, $home, $away, $league, '2030-08-22');
        app(SeasonTicketService::class)->sellForSeason($instance);
        $this->startStandExpansion($home, 200);
        app(StadiumService::class)->completeStandConstructionForInstance($instance, CarbonImmutable::parse('2030-08-27'));
        $home->stadium->forceFill(['ticket_price' => 200])->save();
        $game->forceFill(['status' => Game::STATUS_COMPLETED])->save();
        $balance = $account->fresh()->balance;
        $eventName = 'eloquent.creating: '.FinanceTransactions::class;
        Event::listen($eventName, function (FinanceTransactions $transaction): void {
            if ($transaction->category === 'season_ticket_refunds') {
                throw new RuntimeException('Refund failed.');
            }
        });

        try {
            app(MatchdayService::class)->settle($game);
            $this->fail('Expected the refund to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Refund failed.', $exception->getMessage());
        } finally {
            Event::forget($eventName);
        }
        $this->assertSame($balance, $account->fresh()->balance);
        $this->assertNull($game->fresh()->attendance);
        $this->assertDatabaseCount('matchday_settlements', 0);
        $this->assertDatabaseMissing('finance_transactions', ['category' => 'matchday_operating_costs']);

        $settlement = app(MatchdayService::class)->settle($game);

        $this->assertSame(200, $settlement->attendance);
        $this->assertSame(200, $settlement->season_ticket_refund_quantity);
        $this->assertSame(20000, $settlement->season_ticket_refund_amount);
        $this->assertSame(29500, $account->fresh()->balance);
        $this->assertDatabaseHas('finance_transactions', ['category' => 'season_ticket_refunds', 'amount' => 20000,
            'transaction_date' => '2030-08-22 00:00:00', 'reference' => 'matchday:'.$game->id.':season_ticket_refund']);
    }

    #[Test]
    public function a_fixture_postponed_until_reopening_does_not_refund_season_tickets(): void
    {
        [$instance, $home, $away, $league] = $this->world();
        $game = $this->game($instance, $home, $away, $league, '2030-08-22');
        app(SeasonTicketService::class)->sellForSeason($instance);
        $this->startStandExpansion($home, 200);
        app(StadiumService::class)->completeStandConstructionForInstance($instance, CarbonImmutable::parse('2030-08-27'));

        app(CompleteGameService::class)->postpone($game->id, '2030-08-27 15:00:00');
        $game->forceFill(['status' => Game::STATUS_COMPLETED])->save();
        $settlement = app(MatchdayService::class)->settle($game);

        $this->assertSame(400, $settlement->season_ticket_attendance);
        $this->assertSame(0, $settlement->season_ticket_refund_amount);
        $this->assertDatabaseMissing('finance_transactions', ['category' => 'season_ticket_refunds']);
    }

    #[Test]
    public function delayed_first_sales_use_the_capacity_at_the_match_and_do_not_create_artificial_refunds(): void
    {
        [$instance, $home, $away, $league] = $this->world();
        $game = $this->game($instance, $home, $away, $league, '2030-08-22');
        $this->startStandExpansion($home, 200);
        app(StadiumService::class)->completeStandConstructionForInstance($instance, CarbonImmutable::parse('2030-08-27'));
        $game->forceFill(['status' => Game::STATUS_COMPLETED])->save();

        $settlement = app(MatchdayService::class)->settle($game);

        $sale = SeasonTicketSale::query()->forClub($home)->sole();
        $this->assertSame(200, $sale->capacity);
        $this->assertSame(80, $sale->quantity);
        $this->assertSame(80, $settlement->season_ticket_attendance);
        $this->assertSame(120, $settlement->paying_attendance);
        $this->assertSame(0, $settlement->season_ticket_refund_amount);
        $this->assertDatabaseMissing('finance_transactions', ['category' => 'season_ticket_refunds']);
    }

    private function startStandExpansion(Club $club, int $availableCapacity): StadiumStandConstruction
    {
        StadiumStand::factory()->create(['stadium_id' => $club->stadium_id, 'position' => StadiumStandPosition::NORTH,
            'capacity' => $availableCapacity, 'status' => StadiumStandStatus::ACTIVE]);
        $stand = StadiumStand::factory()->create(['stadium_id' => $club->stadium_id, 'position' => StadiumStandPosition::SOUTH,
            'capacity' => 2000 - $availableCapacity, 'status' => StadiumStandStatus::UNDER_CONSTRUCTION]);

        return StadiumStandConstruction::factory()->create(['instance_id' => $club->instance_id, 'stadium_id' => $club->stadium_id,
            'stadium_stand_id' => $stand->id, 'target_capacity' => 2000 - $availableCapacity, 'capacity_increase' => 1000,
            'started_at' => '2030-08-20', 'completes_at' => '2030-08-27']);
    }

    /** @return array{Instance, Club, Club, Competition, Competition, Competition, Account} */
    private function world(): array
    {
        if (! Country::query()->where('code', 'GBR')->exists()) {
            Country::query()->forceCreate(['code' => 'GBR', 'name' => 'United Kingdom', 'ranking' => 100, 'population' => 60000000]);
        }
        $instance = Instance::factory()->create(['instance_date' => '2030-07-01']);
        $season = Season::factory()->create(['instance_id' => $instance->id, 'start_date' => '2030-08-15', 'end_date' => '2031-06-15']);
        $instance->forceFill(['season_id' => $season->id])->save();
        $stadium = Stadium::factory()->create(['instance_id' => $instance->id, 'capacity' => 1000, 'active_capacity' => 1000, 'ticket_price' => 100, 'country_code' => 'GBR']);
        $home = Club::factory()->create(['instance_id' => $instance->id, 'stadium_id' => $stadium->id, 'rank' => 20, 'country_code' => 'GBR']);
        $away = Club::factory()->create(['instance_id' => $instance->id, 'stadium_id' => $stadium->id, 'rank' => 20, 'country_code' => 'GBR']);
        $league = Competition::factory()->create(['instance_id' => $instance->id, 'type' => 'league', 'rank' => 10000, 'competition_scope' => 'domestic']);
        $cup = Competition::factory()->create(['instance_id' => $instance->id, 'type' => 'tournament', 'rank' => 10000, 'competition_scope' => 'domestic']);
        $continental = Competition::factory()->create(['instance_id' => $instance->id, 'type' => 'tournament', 'rank' => 10000, 'competition_scope' => 'continental']);
        $account = Account::factory()->create(['club_id' => $home->id, 'balance' => 10000, 'future_balance' => 10000]);
        Account::factory()->create(['club_id' => $away->id, 'balance' => 10000, 'future_balance' => 10000]);

        return [$instance, $home, $away, $league, $cup, $continental, $account];
    }

    private function game(Instance $instance, Club $home, Club $away, Competition $competition, string $date): Game
    {
        return Game::factory()->create(['instance_id' => $instance->id, 'season_id' => $instance->season_id, 'competition_id' => $competition->id,
            'hometeam_id' => $home->id, 'awayteam_id' => $away->id, 'stadium_id' => $home->stadium_id, 'attendance' => null, 'match_start' => $date.' 15:00:00']);
    }
}
