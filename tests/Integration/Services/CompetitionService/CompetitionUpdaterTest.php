<?php

namespace Tests\Integration\Services\CompetitionService;

use App\Models\Competition;
use App\Models\Game;
use App\Models\Instance;
use App\Models\Season;
use App\Models\TournamentGroup;
use App\Repositories\CompetitionRepository;
use App\Services\CompetitionService\Competitions\CompetitionUpdater;
use App\Services\CompetitionService\Competitions\LeagueUpdater;
use App\Services\CompetitionService\Competitions\TournamentUpdater;
use App\Services\CompetitionService\DataLayer\CompetitionDataSource;
use App\Services\InstanceService\InstanceData\InitialSeed;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompetitionUpdaterTest extends TestCase
{

    use DatabaseMigrations;

    #[Test]
    public function it_can_update_league_and_tournament_group_points()
    {
        $competitionRepository = new CompetitionRepository((new CompetitionDataSource()));
        $tournamentUpdater     = new TournamentUpdater($competitionRepository);
        $leagueUpdater         = new LeagueUpdater($competitionRepository);
        $competitionUpdater    = new CompetitionUpdater($leagueUpdater, $tournamentUpdater);
        $season                = Season::factory()->create();
        $instance              = Instance::factory()->create();

        (new DatabaseSeeder())->run();
        $init = new InitialSeed();
        $init->seedFromBaseTables(1);

        $gamesByCompetition = [
            1 => [
                [
                    "id"              => 2,
                    "instance_id"     => 1,
                    "season_id"       => 1,
                    "competition_id"  => 1,
                    "hometeam_id"     => 2,
                    "awayteam_id"     => 19,
                    "stadium_id"      => 2,
                    "attendance"      => null,
                    "match_start"     => "2023-08-22 00:00:00",
                    "winner"          => "1",
                    "home_team_goals" => 2,
                    "away_team_goals" => 1,
                    "match_summary"   => null,
                ],
            ],
            6 => [
                [
                    "id"              => 444,
                    "instance_id"     => 1,
                    "season_id"       => 1,
                    "competition_id"  => 6,
                    "hometeam_id"     => 17,
                    "awayteam_id"     => 19,
                    "stadium_id"      => 2,
                    "attendance"      => null,
                    "match_start"     => "2023-08-22 00:00:00",
                    "winner"          => "1",
                    "home_team_goals" => 1,
                    "away_team_goals" => 2,
                    "match_summary"   => null,
                ],
            ],
        ];

        $competition = Competition::factory()->make(['id' => 1]);
        $competition->seasons()->attach(1, ['club_id' => 2, 'instance_id' => 1]);
        $competition->seasons()->attach(1, ['club_id' => 19, 'instance_id' => 1]);
        Game::factory()->create(
            ['instance_id' => 1, 'season_id' => 1, 'competition_id' => 6, 'hometeam_id' => 17, 'awayteam_id' => 19, 'winner' => 1, 'stadium_id' => 1, 'match_summary' => '{}']
        );
        TournamentGroup::factory()->create(
            ['club_id' => 17, 'group_id' => 1, 'instance_id' => 1, 'season_id' => 1, 'competition_id' => 6]
        );
        TournamentGroup::factory()->create(
            ['club_id' => 19, 'group_id' => 1, 'instance_id' => 1, 'season_id' => 1, 'competition_id' => 6]
        );

        $competitionUpdater->setGamesByCompetition($gamesByCompetition);
        $competitionUpdater->distributeGamesForCompetitionUpdates($season, $instance->id);

        $this->assertDatabaseHas(
            'competition_season',
            [
                'club_id' => 2,
                'points'  => 3,
            ]
        );

        $this->assertDatabaseHas(
            'tournament_groups',
            [
                'club_id' => 17,
                'points'  => 3,
            ]
        );
    }
}
