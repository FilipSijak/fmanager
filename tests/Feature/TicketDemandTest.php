<?php

namespace Tests\Feature;

use App\Contracts\Search\PlayerSearchIndexDispatcher;
use App\Events\MonthlyUpdate;
use App\Models\Account;
use App\Models\Club;
use App\Models\ClubDemandProfile;
use App\Models\Competition;
use App\Models\Country;
use App\Models\Game;
use App\Models\Instance;
use App\Models\Season;
use App\Models\SeasonTicketSale;
use App\Models\Stadium;
use App\Repositories\TicketDemandRepository;
use App\Services\CommercialService\CommercialDemandService;
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
        $this->assertSame(181, $expensive->attendance);
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
        $this->assertSame(487, $poorExpensive->attendance);
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
    public function only_the_last_ten_results_before_the_month_affect_demand(): void
    {
        [$game, $club, $instance] = $this->world(66, 100, 10, 5000);
        $this->completedResult($game, '2030-05-01', 5, 0);
        for ($day = 1; $day <= 10; $day++) {
            $this->completedResult($game, sprintf('2030-06-%02d', $day), 0, 2);
        }
        $this->completedResult($game, '2030-09-01', 10, 0);
        $game->forceFill(['home_team_goals' => 9, 'away_team_goals' => 0])->save();
        [$foreignGame] = $this->world(66, 100, 10, 5000);
        $this->completedResult($foreignGame, '2030-07-01', 10, 0);

        $settlement = app(MatchdayService::class)->settle($game);
        $inputs = app(TicketDemandRepository::class)->inputs($club, CarbonImmutable::parse('2030-08-15 15:00:00'));

        $this->assertCount(10, $inputs['results']);
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
        $this->assertSame(72, $expensive->quantity);
        $this->assertLessThanOrEqual(400, $winning->quantity);
        $this->assertSame(21780, $winning->amount);
    }

    #[Test]
    public function a_price_increase_reduces_match_attendance_and_pass_sales_for_a_low_ranked_club_in_poor_form(): void
    {
        [$fairGame, $fairClub, $fairInstance] = $this->world(80, 100, 5, 10000, false);
        [$expensiveGame, $expensiveClub, $expensiveInstance] = $this->world(120, 100, 5, 10000, false);
        for ($day = 1; $day <= 5; $day++) {
            $this->completedResult($fairGame, '2030-06-0'.$day, 0, 2);
            $this->completedResult($expensiveGame, '2030-06-0'.$day, 0, 2);
        }

        $seasonTickets = app(SeasonTicketService::class);
        $seasonTickets->sellForSeason($fairInstance);
        $seasonTickets->sellForSeason($expensiveInstance);
        $fairGame->forceFill(['status' => Game::STATUS_COMPLETED])->save();
        $expensiveGame->forceFill(['status' => Game::STATUS_COMPLETED])->save();
        $matchdays = app(MatchdayService::class);
        $fair = $matchdays->settle($fairGame);
        $expensive = $matchdays->settle($expensiveGame);

        $this->assertSame(255, SeasonTicketSale::query()->forClub($fairClub)->sole()->quantity);
        $this->assertSame(57, SeasonTicketSale::query()->forClub($expensiveClub)->sole()->quantity);
        $this->assertSame(600, $fair->attendance);
        $this->assertSame(136, $expensive->attendance);
        $this->assertSame(57, $expensive->season_ticket_attendance);
        $this->assertSame(9480, $expensive->gate_receipts);
    }

    #[Test]
    public function monthly_reviews_reduce_interest_after_ten_losses_without_changing_ticket_prices(): void
    {
        [$game, $club, $instance] = $this->world(66, 100, 10, 5000);
        $instance->forceFill(['club_id' => $club->id])->save();
        [$foreignGame, $foreignClub] = $this->world(66, 100, 10, 5000);
        for ($day = 1; $day <= 10; $day++) {
            $this->completedResult($game, sprintf('2030-06-%02d', $day), 2, 0);
        }
        $service = app(MatchdayService::class);
        $before = $service->settle($game);
        app(CommercialDemandService::class)->forClub($foreignClub, CarbonImmutable::parse('2030-08-01'));
        for ($day = 16; $day <= 25; $day++) {
            $this->completedResult($game, '2030-08-'.$day, 0, 2);
        }
        $unchanged = app(CommercialDemandService::class)->forClub($club, CarbonImmutable::parse('2030-08-28'));
        $this->mock(PlayerSearchIndexDispatcher::class)->shouldReceive('reindexInstance')->once()->with($instance->id);
        $instance->forceFill(['instance_date' => '2030-09-02'])->save();

        event(new MonthlyUpdate($instance));
        $nextGame = $game->replicate();
        $nextGame->forceFill(['match_start' => '2030-09-15 15:00:00', 'attendance' => null])->save();
        $after = $service->settle($nextGame);

        $this->assertSame(1.0, $unchanged->performance_score);
        $this->assertSame(825, $before->attendance);
        $this->assertSame(625, $after->attendance);
        $this->assertSame(66, $after->ticket_price);
        $this->assertSame(66, $club->stadium->ticket_price);
        $this->assertDatabaseHas('club_demand_profiles', ['club_id' => $club->id, 'period_start' => '2030-09-01',
            'performance_score' => 0, 'ticket_price_sensitivity' => 2.5, 'commercial_price_sensitivity' => 2.5]);
        $this->assertDatabaseMissing('club_demand_profiles', ['club_id' => $foreignClub->id, 'period_start' => '2030-09-01']);
        $this->assertSame(2, ClubDemandProfile::query()->forClub($club)->count());
    }

    #[Test]
    public function ticket_sales_and_matchdays_use_the_stored_monthly_sensitivity(): void
    {
        [$game, $club, $instance] = $this->world(99, 100, 10, 5000, false);
        $instance->forceFill(['instance_date' => '2030-08-01'])->save();
        $profile = ClubDemandProfile::factory()->create(['instance_id' => $instance->id, 'club_id' => $club->id,
            'ticket_price_sensitivity' => 3.0]);

        app(SeasonTicketService::class)->sellForSeason($instance);
        $game->forceFill(['status' => Game::STATUS_COMPLETED])->save();
        $settlement = app(MatchdayService::class)->settle($game);

        $this->assertSame(59, SeasonTicketSale::query()->forClub($club)->sole()->quantity);
        $this->assertSame(150, $settlement->attendance);
        $this->assertSame(9009, $settlement->gate_receipts);
        $this->assertSame($profile->id, app(CommercialDemandService::class)->forClub($club, CarbonImmutable::parse('2030-08-31'))->id);
        $this->assertSame(1, ClubDemandProfile::query()->forClub($club)->count());
    }

    #[Test]
    public function monthly_profiles_capture_rank_changes_and_recover_with_better_form_without_rewriting_history(): void
    {
        [$game, $club, $instance] = $this->world(66, 100, 10, 5000);
        $this->completedResult($game, '2030-06-01', 1, 1);
        $demand = app(CommercialDemandService::class);
        $august = $demand->forClub($club, CarbonImmutable::parse('2030-08-01'));
        $club->forceFill(['rank' => 5])->save();
        Country::query()->where('code', 'GBR')->update(['ranking' => 50]);
        for ($day = 1; $day <= 10; $day++) {
            $this->completedResult($game, sprintf('2030-08-%02d', $day), 0, 2);
            $this->completedResult($game, sprintf('2030-09-%02d', $day), 2, 0);
        }

        $september = $demand->forClub($club, CarbonImmutable::parse('2030-09-01'));
        $october = $demand->forClub($club, CarbonImmutable::parse('2030-10-01'));
        $repeatedAugust = $demand->forClub($club, CarbonImmutable::parse('2030-08-31'));

        $this->assertSame(0.333333, $august->performance_score);
        $this->assertSame($august->getAttributes(), $repeatedAugust->getAttributes());
        $this->assertSame(5, $september->club_rank);
        $this->assertSame(50, $september->country_rank);
        $this->assertSame(0.0, $september->performance_score);
        $this->assertSame(3.75, $september->ticket_price_sensitivity);
        $this->assertSame(1.0, $october->performance_score);
        $this->assertSame(2.75, $october->ticket_price_sensitivity);
        $this->assertSame(2.75, $october->commercial_price_sensitivity);
        $this->assertSame(66, $club->stadium->ticket_price);
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
