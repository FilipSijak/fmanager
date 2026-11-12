<?php

namespace Tests\Feature;

use App\Events\SeasonStarted;
use App\Listeners\SellSeasonTickets;
use App\Models\Account;
use App\Models\Club;
use App\Models\Competition;
use App\Models\Game;
use App\Models\Instance;
use App\Models\Season;
use App\Models\SeasonTicketSale;
use App\Models\Stadium;
use App\Services\CommercialService\Domain\TicketType;
use App\Services\MatchdayService\MatchdayService;
use App\Services\SeasonTicketService\SeasonTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
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
        $this->assertDatabaseHas('season_ticket_sales', ['club_id' => $home->id, 'season_id' => $nextSeason->id, 'ticket_price' => 200, 'amount' => 80000]);
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

    /** @return array{Instance, Club, Club, Competition, Competition, Competition, Account} */
    private function world(): array
    {
        $instance = Instance::factory()->create(['instance_date' => '2030-07-01']);
        $season = Season::factory()->create(['instance_id' => $instance->id, 'start_date' => '2030-08-15', 'end_date' => '2031-06-15']);
        $instance->forceFill(['season_id' => $season->id])->save();
        $stadium = Stadium::factory()->create(['instance_id' => $instance->id, 'capacity' => 1000, 'active_capacity' => 1000, 'ticket_price' => 100]);
        $home = Club::factory()->create(['instance_id' => $instance->id, 'stadium_id' => $stadium->id, 'rank' => 20]);
        $away = Club::factory()->create(['instance_id' => $instance->id, 'stadium_id' => $stadium->id, 'rank' => 20]);
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
