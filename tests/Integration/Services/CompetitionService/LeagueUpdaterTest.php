<?php

namespace Tests\Integration\Services\CompetitionService;

use App\Models\Competition;
use App\Models\Game;
use App\Repositories\Competition\CompetitionStandingsRepository;
use App\Services\CompetitionService\Competitions\LeagueUpdater;
use App\Support\GameContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LeagueUpdaterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_update_table_points()
    {
        app(GameContext::class)->set(1, 1);
        $games = [];
        $gameContext = [
            'instance_id' => 1,
            'season_id' => 1,
            'competition_id' => 1,
            'hometeam_id' => 1,
            'awayteam_id' => 2,
        ];
        $games[] = Game::factory()->make($gameContext + ['winner' => 1, 'home_team_goals' => 3, 'away_team_goals' => 1])->toArray();
        $games[] = Game::factory()->make($gameContext + ['winner' => 2, 'home_team_goals' => 0, 'away_team_goals' => 2])->toArray();
        $games[] = Game::factory()->make($gameContext + ['winner' => 3, 'home_team_goals' => 2, 'away_team_goals' => 2])->toArray();
        $competition = Competition::factory()->make(['id' => 1]);

        $competition->seasons()->attach(1, ['club_id' => 1, 'instance_id' => 1]);
        $competition->seasons()->attach(1, ['club_id' => 2, 'instance_id' => 1]);

        $repository = app(CompetitionStandingsRepository::class);
        (new LeagueUpdater($repository))->updatePointsTable($games);

        $this->assertDatabaseHas(
            'competition_season',
            [
                'club_id' => 1,
                'points' => 4,
                'played' => 3,
                'wins' => 1,
                'draws' => 1,
                'losses' => 1,
                'goals_for' => 5,
                'goals_against' => 5,
            ]
        );

        $this->assertDatabaseHas(
            'competition_season',
            [
                'club_id' => 2,
                'points' => 4,
                'played' => 3,
                'wins' => 1,
                'draws' => 1,
                'losses' => 1,
                'goals_for' => 5,
                'goals_against' => 5,
            ]
        );
    }

    #[Test]
    public function it_rolls_back_both_standings_updates_when_a_membership_is_missing(): void
    {
        app(GameContext::class)->set(1, 1);
        $competition = Competition::factory()->make(['id' => 1]);
        $competition->seasons()->attach(1, ['club_id' => 1, 'instance_id' => 1]);
        $repository = app(CompetitionStandingsRepository::class);

        try {
            $repository->update(Game::factory()->make([
                'instance_id' => 1,
                'season_id' => 1,
                'competition_id' => 1,
                'hometeam_id' => 1,
                'awayteam_id' => 2,
                'winner' => 1,
                'home_team_goals' => 2,
                'away_team_goals' => 0,
            ])->toArray());
            $this->fail('Expected a missing standings row to fail.');
        } catch (LogicException) {
            $this->assertDatabaseHas('competition_season', [
                'club_id' => 1,
                'played' => 0,
                'points' => 0,
            ]);
        }
    }
}
