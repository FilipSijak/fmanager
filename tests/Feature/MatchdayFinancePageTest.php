<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BaseData\BaseCommercialCategory;
use App\Models\BaseData\BaseCommercialProducts;
use App\Models\Club;
use App\Models\Competition;
use App\Models\Country;
use App\Models\Game;
use App\Models\Instance;
use App\Models\Season;
use App\Models\Stadium;
use App\Models\StadiumCommercialVenue;
use App\Models\StadiumStand;
use App\Services\MatchdayService\MatchdayService;
use App\StadiumStandPosition;
use App\StadiumStandStatus;
use App\Support\GameContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class MatchdayFinancePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    #[Test]
    public function finance_shows_saved_income_refunds_and_upfront_pass_sales_without_posting_again(): void
    {
        [$instance, $club, $season] = $this->world();
        $first = $this->game($club, $season, '2030-09-01');
        $second = $this->game($club, $season, '2030-09-15');
        $this->settle($first);
        StadiumStand::factory()->create(['stadium_id' => $club->stadium_id, 'position' => StadiumStandPosition::NORTH, 'capacity' => 200, 'status' => StadiumStandStatus::ACTIVE]);
        StadiumStand::factory()->create(['stadium_id' => $club->stadium_id, 'position' => StadiumStandPosition::SOUTH, 'capacity' => 800, 'status' => StadiumStandStatus::UNDER_CONSTRUCTION]);
        $this->settle($second);
        $balance = $club->account->balance;

        $this->visit($instance)->get('/finance')->assertInertia(fn (Assert $page) => $page->component('Finance')
            ->where('matchdayFinances.summary.matches', 2)
            ->where('matchdayFinances.summary.attendance', 1200)
            ->where('matchdayFinances.summary.average_attendance', 600)
            ->where('matchdayFinances.summary.gate_receipts', 60000)
            ->where('matchdayFinances.summary.commercial_income', 1200)
            ->where('matchdayFinances.summary.operating_costs', 3300)
            ->where('matchdayFinances.summary.season_ticket_refunds', 14000)
            ->where('matchdayFinances.summary.net_income', 43900)
            ->where('matchdayFinances.summary.season_ticket_income', 56000)
            ->where('matchdayFinances.summary.net_including_season_tickets', 99900)
            ->where('matchdayFinances.season_tickets.quantity', 400)
            ->where('matchdayFinances.season_tickets.home_games', 2)
            ->where('matchdayFinances.season_tickets.ticket_price', 140)
            ->has('matchdayFinances.matches', 2)
            ->where('matchdayFinances.matches.0.game_id', $second->id)
            ->where('matchdayFinances.matches.0.net_income', -14450)
            ->where('matchdayFinances.matches.0.season_ticket_refund_quantity', 200)
            ->where('matchdayFinances.matches.1.net_income', 58350));

        $this->assertSame($balance, $club->account->fresh()->balance);
        $this->assertDatabaseCount('matchday_settlements', 2);
        $this->assertDatabaseCount('finance_transactions', 7);
    }

    #[Test]
    public function finance_only_includes_the_managed_clubs_current_season_in_the_active_instance(): void
    {
        [$instance, $club, $season] = $this->world();
        $own = $this->game($club, $season, '2030-09-01');
        $this->settle($own);
        [$foreignInstance, $foreignClub, $foreignSeason] = $this->world();
        $this->settle($this->game($foreignClub, $foreignSeason, '2030-09-02'));
        $other = Club::factory()->create(['instance_id' => $instance->id, 'stadium_id' => $club->stadium_id, 'country_code' => 'GBR', 'rank' => 20]);
        Account::factory()->create(['club_id' => $other->id]);
        $this->settle($this->game($other, $season, '2030-09-03'));
        $oldSeason = Season::factory()->create(['instance_id' => $instance->id, 'start_date' => '2029-08-15', 'end_date' => '2030-06-15']);
        $this->settle($this->game($club, $oldSeason, '2029-09-01'));

        $this->visit($instance)->get('/finance?club_id='.$foreignClub->id.'&season_id='.$oldSeason->id)
            ->assertInertia(fn (Assert $page) => $page->where('matchdayFinances.summary.matches', 1)
                ->where('matchdayFinances.summary.season_ticket_income', 28000)
                ->has('matchdayFinances.matches', 1)->where('matchdayFinances.matches.0.game_id', $own->id));
    }

    #[Test]
    public function match_history_is_paginated_but_totals_cover_the_whole_season(): void
    {
        [$instance, $club, $season] = $this->world();
        $games = [];
        for ($day = 1; $day <= 13; $day++) {
            $games[] = $this->game($club, $season, sprintf('2030-09-%02d', $day));
        }
        foreach ($games as $game) {
            $this->settle($game);
        }

        $this->visit($instance)->get('/finance?matchday_page=2')->assertInertia(fn (Assert $page) => $page
            ->has('matchdayFinances.matches', 3)
            ->where('matchdayFinances.matches.0.game_id', $games[2]->id)
            ->where('matchdayFinances.pagination.current_page', 2)
            ->where('matchdayFinances.pagination.last_page', 2)
            ->where('matchdayFinances.pagination.total', 13)
            ->where('matchdayFinances.pagination.from', 11)
            ->where('matchdayFinances.pagination.to', 13)
            ->where('matchdayFinances.summary.matches', 13)
            ->where('matchdayFinances.summary.net_income', 758550));
    }

    #[Test]
    public function an_unplayed_season_has_no_invented_matchday_revenue_or_season_ticket_sales(): void
    {
        [$instance] = $this->world();

        $this->visit($instance)->get('/finance')->assertInertia(fn (Assert $page) => $page
            ->where('matchdayFinances.summary.matches', 0)->where('matchdayFinances.summary.net_income', 0)
            ->where('matchdayFinances.summary.season_ticket_income', 0)
            ->where('matchdayFinances.season_tickets', null)->has('matchdayFinances.matches', 0));
    }

    #[Test]
    #[TestWith(['0'])]
    #[TestWith(['not-a-page'])]
    #[TestWith(['1.5'])]
    #[TestWith(['100001'])]
    public function invalid_matchday_page_numbers_are_rejected(string $page): void
    {
        [$instance] = $this->world();

        $this->visit($instance)->getJson('/finance?matchday_page='.$page)->assertUnprocessable()
            ->assertJsonPath('errors.matchday_page.0', 'Choose a valid matchday page.');
    }

    #[Test]
    public function home_match_pages_show_the_saved_breakdown_and_preserve_legacy_unknown_values(): void
    {
        [$instance, $club, $season] = $this->world();
        $game = $this->game($club, $season, '2030-09-01');
        $this->settle($game);
        $game->matchdaySettlement()->firstOrFail()->forceFill(['paying_attendance' => null, 'ticket_type' => null])->save();

        $this->visit($instance)->get('/matches/'.$game->id)->assertInertia(fn (Assert $page) => $page
            ->where('isManagedHomeMatch', true)->where('matchdayFinances.game_id', $game->id)
            ->where('matchdayFinances.gate_receipts', 60000)->where('matchdayFinances.commercial_income', 1000)
            ->where('matchdayFinances.operating_costs', 2650)->where('matchdayFinances.net_income', 58350)
            ->where('matchdayFinances.paying_attendance', null)->where('matchdayFinances.ticket_type', null));
    }

    #[Test]
    public function historic_home_matches_keep_their_breakdown_without_switching_the_active_season(): void
    {
        [$instance, $club, $season] = $this->world();
        $oldSeason = Season::factory()->create(['instance_id' => $instance->id, 'start_date' => '2029-08-15', 'end_date' => '2030-06-15']);
        $game = $this->game($club, $oldSeason, '2029-09-01');
        $this->settle($game);

        $this->visit($instance)->get('/matches/'.$game->id)->assertInertia(fn (Assert $page) => $page
            ->where('match.season_id', $oldSeason->id)->where('matchdayFinances.game_id', $game->id));

        $this->assertSame($season->id, app(GameContext::class)->seasonId());
    }

    #[Test]
    public function an_opponents_home_finances_are_private_even_when_the_match_is_visible(): void
    {
        [$instance, $club, $season] = $this->world();
        $other = Club::factory()->create(['instance_id' => $instance->id, 'stadium_id' => $club->stadium_id, 'country_code' => 'GBR', 'rank' => 20]);
        Account::factory()->create(['club_id' => $other->id]);
        $game = $this->game($other, $season, '2030-09-01');
        $game->forceFill(['awayteam_id' => $club->id])->save();
        $this->settle($game);

        $this->visit($instance)->get('/matches/'.$game->id)->assertInertia(fn (Assert $page) => $page
            ->where('isManagedHomeMatch', false)->where('matchdayFinances', null));
    }

    #[Test]
    #[TestWith(['scheduled'])]
    #[TestWith(['completed'])]
    public function matches_without_a_saved_settlement_have_no_invented_financial_breakdown(string $status): void
    {
        [$instance, $club, $season] = $this->world();
        $game = $this->game($club, $season, '2030-09-01');
        $game->forceFill(['status' => $status])->save();

        $this->visit($instance)->get('/matches/'.$game->id)->assertInertia(fn (Assert $page) => $page
            ->where('isManagedHomeMatch', true)->where('matchdayFinances', null));
    }

    #[Test]
    public function an_invalid_cross_instance_managed_club_cannot_expose_foreign_finances(): void
    {
        [$instance] = $this->world();
        [, $foreignClub] = $this->world();
        $instance->forceFill(['club_id' => $foreignClub->id])->save();

        $this->visit($instance)->get('/finance')->assertNotFound();
    }

    private function visit(Instance $instance): self
    {
        return $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash]);
    }

    private function settle(Game $game): void
    {
        $game->forceFill(['status' => Game::STATUS_COMPLETED])->save();
        app(MatchdayService::class)->settle($game);
    }

    private function game(Club $club, Season $season, string $date): Game
    {
        $away = Club::factory()->create(['instance_id' => $club->instance_id, 'country_code' => 'GBR', 'name' => 'Visitors FC', 'rank' => 20]);
        $competition = Competition::factory()->create(['instance_id' => $club->instance_id, 'name' => 'League', 'type' => 'league', 'competition_scope' => 'domestic', 'rank' => 10000]);

        return Game::factory()->create(['instance_id' => $club->instance_id, 'season_id' => $season->id,
            'competition_id' => $competition->id, 'hometeam_id' => $club->id, 'awayteam_id' => $away->id,
            'stadium_id' => $club->stadium_id, 'match_start' => $date.' 15:00:00', 'home_team_goals' => 2, 'away_team_goals' => 1]);
    }

    /** @return array{Instance, Club, Season} */
    private function world(): array
    {
        if (! Country::query()->where('code', 'GBR')->exists()) {
            Country::query()->forceCreate(['code' => 'GBR', 'name' => 'United Kingdom', 'ranking' => 100, 'population' => 60000000]);
        }
        $instance = Instance::factory()->create(['instance_date' => '2030-09-30']);
        $season = Season::factory()->create(['instance_id' => $instance->id, 'start_date' => '2030-08-15', 'end_date' => '2031-06-15']);
        $stadium = Stadium::factory()->create(['instance_id' => $instance->id, 'capacity' => 1000, 'active_capacity' => 1000, 'ticket_price' => 100]);
        $club = Club::factory()->create(['instance_id' => $instance->id, 'stadium_id' => $stadium->id, 'country_code' => 'GBR', 'rank' => 20]);
        $instance->forceFill(['club_id' => $club->id, 'season_id' => $season->id])->save();
        Account::factory()->create(['club_id' => $club->id, 'balance' => 10000, 'future_balance' => 10000]);
        $category = BaseCommercialCategory::query()->firstOrCreate(['slug' => 'bar'], ['name' => 'Bar']);
        if (! BaseCommercialProducts::query()->where('category_id', $category->id)->exists()) {
            BaseCommercialProducts::query()->forceCreate(['slug' => 'drinks', 'name' => 'Drinks', 'category_id' => $category->id, 'base_price' => 500]);
        }
        StadiumCommercialVenue::factory()->create(['instance_id' => $instance->id, 'stadium_id' => $stadium->id, 'category_id' => $category->id, 'size' => 1]);

        return [$instance, $club, $season];
    }
}
