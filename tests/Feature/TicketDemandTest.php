<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Club;
use App\Models\Competition;
use App\Models\Country;
use App\Models\Game;
use App\Models\Instance;
use App\Models\Season;
use App\Models\SeasonTicketSale;
use App\Models\Stadium;
use App\Repositories\TicketDemandRepository;
use App\Services\CommercialService\Domain\TicketDemandCalculator;
use App\Services\MatchdayService\MatchdayService;
use App\Services\SeasonTicketService\SeasonTicketService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TicketDemandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function matchday_demand_responds_to_prices_above_the_rich_country_tolerance(): void
    {
        [$fairGame] = $this->world(100);
        [$toleratedGame] = $this->world(110);
        [$expensiveGame] = $this->world(200);
        $service = app(MatchdayService::class);

        $fair = $service->settle($fairGame);
        $tolerated = $service->settle($toleratedGame);
        $expensive = $service->settle($expensiveGame);

        $this->assertSame(1000, $fair->attendance);
        $this->assertSame(1000, $tolerated->attendance);
        $this->assertSame(277, $expensive->attendance);
        $this->assertLessThan($fair->gate_receipts, $expensive->gate_receipts);
    }

    #[Test]
    public function country_context_changes_price_tolerance_and_affordability(): void
    {
        [$richGame] = $this->world(110);
        [$poorFairGame] = $this->world(78, 0);
        [$poorExpensiveGame] = $this->world(86, 0);
        $service = app(MatchdayService::class);

        $rich = $service->settle($richGame);
        $poorFair = $service->settle($poorFairGame);
        $poorExpensive = $service->settle($poorExpensiveGame);

        $this->assertSame(1000, $rich->attendance);
        $this->assertSame(900, $poorFair->attendance);
        $this->assertSame(526, $poorExpensive->attendance);
    }

    #[Test]
    public function cheaper_prices_increase_unsaturated_demand(): void
    {
        [$fairGame] = $this->world(66, 100, 10, 5000);
        [$cheapGame] = $this->world(33, 100, 10, 5000);
        $service = app(MatchdayService::class);

        $fair = $service->settle($fairGame);
        $cheap = $service->settle($cheapGame);

        $this->assertSame(725, $fair->attendance);
        $this->assertSame(906, $cheap->attendance);
    }

    #[Test]
    public function only_the_last_five_results_before_kickoff_affect_demand(): void
    {
        [$game, $club, $instance] = $this->world(66, 100, 10, 5000);
        $this->completedResult($game, '2030-05-01', 5, 0);
        for ($day = 1; $day <= 5; $day++) {
            $this->completedResult($game, '2030-06-0'.$day, 0, 2);
        }
        $this->completedResult($game, '2030-09-01', 10, 0);
        $game->forceFill(['home_team_goals' => 9, 'away_team_goals' => 0])->save();
        [$foreignGame] = $this->world(66, 100, 10, 5000);
        $this->completedResult($foreignGame, '2030-07-01', 10, 0);

        $settlement = app(MatchdayService::class)->settle($game);
        $inputs = app(TicketDemandRepository::class)->inputs($club, CarbonImmutable::parse('2030-08-15 15:00:00'));

        $this->assertCount(5, $inputs['results']);
        $this->assertSame(0.0, app(TicketDemandCalculator::class)->performanceScore($club->id, $inputs['results']));
        $this->assertSame(625, $settlement->attendance);
    }

    #[Test]
    public function season_ticket_sales_use_form_and_price_demand_under_the_forty_percent_cap(): void
    {
        [$winningGame, $winningClub, $winningInstance] = $this->world(66, 100, 10, 5000, false);
        [$losingGame, $losingClub, $losingInstance] = $this->world(66, 100, 10, 5000, false);
        [$expensiveGame, $expensiveClub, $expensiveInstance] = $this->world(200, 100, 20, 10000, false);
        for ($day = 1; $day <= 5; $day++) {
            $this->completedResult($winningGame, '2030-06-0'.$day, 2, 0);
            $this->completedResult($losingGame, '2030-06-0'.$day, 0, 2);
        }
        $service = app(SeasonTicketService::class);

        $service->sellForSeason($winningInstance);
        $service->sellForSeason($losingInstance);
        $service->sellForSeason($expensiveInstance);

        $winning = SeasonTicketSale::query()->forClub($winningClub)->sole();
        $losing = SeasonTicketSale::query()->forClub($losingClub)->sole();
        $expensive = SeasonTicketSale::query()->forClub($expensiveClub)->sole();
        $this->assertSame(330, $winning->quantity);
        $this->assertSame(250, $losing->quantity);
        $this->assertSame(110, $expensive->quantity);
        $this->assertLessThanOrEqual(400, $winning->quantity);
        $this->assertSame(21780, $winning->amount);
    }

    /** @return array{Game, Club, Instance} */
    private function world(int $price, int $countryRank = 100, int $clubRank = 20, int $competitionRank = 10000, bool $completed = true): array
    {
        $code = $countryRank === 100 ? 'GBR' : 'LOW';
        if (! Country::query()->where('code', $code)->exists()) {
            Country::query()->forceCreate(['code' => $code, 'name' => $code, 'ranking' => $countryRank, 'population' => 1000000]);
        }
        $instance = Instance::factory()->create(['instance_date' => '2030-07-01']);
        $season = Season::factory()->create(['instance_id' => $instance->id, 'start_date' => '2030-08-15', 'end_date' => '2031-06-15']);
        $instance->forceFill(['season_id' => $season->id])->save();
        $stadium = Stadium::factory()->create(['instance_id' => $instance->id, 'country_code' => $code, 'capacity' => 1000, 'active_capacity' => 1000, 'ticket_price' => $price]);
        $club = Club::factory()->create(['instance_id' => $instance->id, 'stadium_id' => $stadium->id, 'country_code' => $code, 'rank' => $clubRank]);
        $away = Club::factory()->create(['instance_id' => $instance->id, 'stadium_id' => $stadium->id, 'country_code' => $code, 'rank' => $clubRank]);
        Account::factory()->create(['club_id' => $club->id]);
        Account::factory()->create(['club_id' => $away->id]);
        $competition = Competition::factory()->create(['instance_id' => $instance->id, 'rank' => $competitionRank, 'type' => 'league', 'competition_scope' => 'domestic']);
        $game = Game::factory()->create(['instance_id' => $instance->id, 'season_id' => $season->id, 'competition_id' => $competition->id,
            'hometeam_id' => $club->id, 'awayteam_id' => $away->id, 'stadium_id' => $stadium->id, 'match_start' => '2030-08-15 15:00:00',
            'attendance' => null, 'status' => $completed ? Game::STATUS_COMPLETED : Game::STATUS_SCHEDULED]);

        return [$game, $club, $instance];
    }

    private function completedResult(Game $game, string $date, int $homeGoals, int $awayGoals): void
    {
        Game::factory()->create(['instance_id' => $game->instance_id, 'season_id' => $game->season_id, 'competition_id' => $game->competition_id,
            'hometeam_id' => $game->hometeam_id, 'awayteam_id' => $game->awayteam_id, 'stadium_id' => $game->stadium_id,
            'match_start' => $date.' 15:00:00', 'status' => Game::STATUS_COMPLETED, 'home_team_goals' => $homeGoals, 'away_team_goals' => $awayGoals]);
    }
}
