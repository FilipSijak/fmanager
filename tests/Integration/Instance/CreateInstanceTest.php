<?php

namespace Tests\Integration\Instance;

use App\Models\Club;
use App\Models\Competition;
use App\Models\Game;
use App\Models\Instance;
use App\Models\Manager;
use App\Models\Player;
use App\Models\Season;
use App\Models\User;
use App\Repositories\CompetitionRepository;
use App\Repositories\PlayerRepository;
use App\Services\CompetitionService\Competitions\LeagueUpdater;
use App\Services\CompetitionService\Competitions\TournamentUpdater;
use App\Services\CompetitionService\CompetitionService;
use App\Services\CompetitionService\DataLayer\CompetitionDataSource;
use App\Services\InstanceService\CreateInstance;
use App\Services\InstanceService\InstanceData\InitialSeed;
use App\Services\PersonService\GeneratePeople\PlayerPotential;
use App\Services\PersonService\PersonService;
use Database\Seeders\ClubsSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateInstanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clubId         = Club::factory()->make(['id' => 1])->id;
        $this->managerId      = Manager::factory()->make(['id' => 1])->id;
        $this->userId         = User::factory()->make(['id' => 1])->id;
    }

    /** @test */
    public function it_can_setup_a_new_game()
    {
        $createInstance = $this->getNewInstance();
        (new DatabaseSeeder())->run();
        $createInstance->instanceInit();
        $instance = Instance::all()->first();
        $competition = Competition::all()->first();
        $tournament = Competition::where('type', 'tournament')->where('groups', 0)->first();
        $tournamentGroup = Competition::where('type', 'tournament')->where('groups', 1)->first();
        $season = Season::where('id', $instance->id)->first();

        $this->assertDatabaseHas(
            'instances',
            [
                'id'         => $instance->id,
                'club_id'    => $this->clubId,
                'user_id'    => $this->userId,
                'manager_id' => $this->managerId,
            ]
        );

        $this->assertDatabaseHas(
            'games',
            [
                'instance_id' => $instance->id,
                'competition_id' => $competition->id,
                'season_id' => $season->id
            ]
        );

        $this->assertDatabaseHas(
            'games',
            [
                'instance_id' => $instance->id,
                'competition_id' => $tournament->id,
                'season_id' => $season->id
            ]
        );

        $this->assertDatabaseHas(
            'tournament_knockout',
            [
                'instance_id' => $instance->id,
                'season_id' => $season->id,
                'competition_id' => $tournament->id
            ]
        );

        $this->assertDatabaseHas(
            'tournament_groups',
            [
                'instance_id' => $instance->id,
                'season_id' => $season->id,
                'competition_id' => $tournamentGroup->id
            ]
        );

        $this->assertDatabaseHas(
            'seasons',
            [
                'id'         => $instance->id,
                'start_date' => $season->start_date,
                'end_date'   => $season->end_date,
            ]
        );

        $club = Club::all()->first();
        $players = Player::where('instance_id', $instance->id)->where('club_id', $club->id)->get();

        //atm each club should have 36 players assigned when creating a game
        $this->assertEquals(36, $players->count());
    }

    protected function getNewInstance(): CreateInstance
    {
        $this->competitionDataSource = new CompetitionDataSource();
        $this->competitionRepository = new CompetitionRepository($this->competitionDataSource);
        $this->competitionService = new CompetitionService(
            (new LeagueUpdater($this->competitionRepository)),
            (new TournamentUpdater($this->competitionRepository))
        );
        $this->personService = new PersonService();

        return (
            new CreateInstance(
                app()->make(CompetitionService::class),
                app()->make(PersonService::class),
                app()->make(CompetitionRepository::class),
                app()->make(PlayerPotential::class),
                app()->make(PlayerRepository::class)
            )
        );
    }
}
