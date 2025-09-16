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
        $games[]     = Game::factory()->make(['winner' => 1, 'hometeam_id' => 1, 'awayteam_id' => 2])->toArray();
        $games[]     = Game::factory()->make(['winner' => 2, 'hometeam_id' => 1, 'awayteam_id' => 2])->toArray();
        $games[]     = Game::factory()->make(['winner' => 3, 'hometeam_id' => 1, 'awayteam_id' => 2])->toArray();
        $competition = Competition::factory()->make(['id' => 1]);

        $competition->seasons()->attach(1, ['club_id' => 1, 'instance_id' => 1]);
        $competition->seasons()->attach(1, ['club_id' => 2, 'instance_id' => 1]);

        (new LeagueUpdater((new CompetitionRepository((new CompetitionDataSource())))))->updatePointsTable($games);

        $this->assertDatabaseHas(
            'competition_season',
            [
                'club_id' => 1,
                'points'  => 4,
            ]
        );

        $this->assertDatabaseHas(
            'competition_season',
            [
                'club_id' => 2,
                'points'  => 4,
            ]
        );
    }
}
