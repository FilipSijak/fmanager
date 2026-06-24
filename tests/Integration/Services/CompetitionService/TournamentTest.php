<?php

namespace Tests\Integration\Services\CompetitionService;

use App\Models\Club;
use App\Models\Game;
use App\Models\Season;
use App\Repositories\CompetitionRepository;
use App\Services\CompetitionService\Competitions\LeagueUpdater;
use App\Services\CompetitionService\Competitions\TournamentUpdater;
use App\Services\CompetitionService\CompetitionService;
use App\Services\CompetitionService\DataLayer\CompetitionDataSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TournamentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_group_stage()
    {
        $season = Season::factory()->create([
            'id' => 1,
            'instance_id' => 1,
            'start_date' => '2026-08-15',
            'end_date' => '2027-08-15',
        ]);
        for ($i = 1; $i <= 8; $i++) {
            Club::factory()->create([
                'instance_id' => 1,
                'stadium_id' => 1000 + $i,
            ]);
        }

        $clubs = Club::all();
        $competitionRepository = new CompetitionRepository((new CompetitionDataSource()));
        $tournamentUpdater     = new TournamentUpdater($competitionRepository);
        $leagueUpdater         = new LeagueUpdater($competitionRepository);
        $competitionService = new CompetitionService($leagueUpdater, $tournamentUpdater, new CompetitionDataSource());

        $competitionService->makeTournamentGroupStage($clubs, 1, $season->id, 1);

        $this->assertDatabaseCount('tournament_groups', 8);
        $this->assertDatabaseCount('games', 24);

        $clubGroups = [];
        foreach (\DB::table('tournament_groups')->get() as $groupRow) {
            $clubGroups[$groupRow->club_id] = $groupRow->group_id;
        }

        foreach (Game::all() as $game) {
            $this->assertSame(1, $game->instance_id);
            $this->assertSame($season->id, $game->season_id);
            $this->assertSame(1, $game->competition_id);
            $this->assertContains((int) \Carbon\Carbon::parse($game->match_start)->format('N'), [2, 3]);
            $this->assertSame($clubGroups[$game->hometeam_id], $clubGroups[$game->awayteam_id]);
        }
    }
}
