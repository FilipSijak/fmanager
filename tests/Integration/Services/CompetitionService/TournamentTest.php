<?php

namespace Tests\Integration\Services\CompetitionService;

use App\Models\Club;
use App\Repositories\CompetitionRepository;
use App\Services\CompetitionService\Competitions\LeagueUpdater;
use App\Services\CompetitionService\Competitions\TournamentUpdater;
use App\Services\CompetitionService\CompetitionService;
use App\Services\CompetitionService\DataLayer\CompetitionDataSource;
use App\Services\InstanceService\InstanceData\InitialSeed;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class TournamentTest extends TestCase
{
    use DatabaseMigrations;

    /** @test */
    public function it_can_create_group_stage()
    {
        Club::factory()->count(8)->create();

        $clubs = Club::all();
        $competitionRepository = new CompetitionRepository((new CompetitionDataSource()));
        $tournamentUpdater     = new TournamentUpdater($competitionRepository);
        $leagueUpdater         = new LeagueUpdater($competitionRepository);
        $competitionService = new CompetitionService($leagueUpdater, $tournamentUpdater);

        $competitionService->makeTournamentGroupStage($clubs, 1, 1, 1);
    }
}
