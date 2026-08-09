<?php

namespace Tests\Integration\Services\CompetitionService;

use App\Models\Competition;
use App\Models\Game;
use App\Repositories\CompetitionRepository;
use App\Services\CompetitionService\Competitions\LeagueUpdater;
use App\Services\CompetitionService\DataLayer\CompetitionDataSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LeagueUpdaterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_update_table_points()
    {
        $games       = [];
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

        (new LeagueUpdater((new CompetitionRepository((new CompetitionDataSource())))))->updatePointsTable($games);

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
}
