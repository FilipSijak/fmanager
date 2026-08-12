<?php

namespace Tests\Integration\Instance;

use App\Models\Club;
use App\Models\Competition;
use App\Models\Game;
use App\Models\Instance;
use App\Models\Manager;
use App\Models\Player;
use App\Models\Season;
use App\Models\StaffCoaching;
use App\Models\StaffPhysio;
use App\Models\StaffScout;
use App\Models\User;
use App\Repositories\CompetitionRepository;
use App\Repositories\PlayerRepository;
use App\Repositories\StaffRepository;
use App\Services\CompetitionService\Competitions\LeagueUpdater;
use App\Services\CompetitionService\Competitions\TournamentUpdater;
use App\Services\CompetitionService\CompetitionService;
use App\Services\CompetitionService\DataLayer\CompetitionDataSource;
use App\Services\InstanceService\CreateInstance;
use App\Services\PersonService\GeneratePeople\PlayerPotential;
use App\Services\PersonService\GeneratePeople\StaffGenerator;
use App\Services\PersonService\PersonService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreateInstanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clubId = Club::factory()->make(['id' => 1])->id;
        $this->managerId = Manager::factory()->make(['id' => 1])->id;
        $this->userId = User::factory()->make(['id' => 1])->id;
    }

    #[Test]
    public function it_can_setup_a_new_game()
    {
        $createInstance = $this->getNewInstance();
        (new DatabaseSeeder)->run();
        $createInstance->instanceInit();
        $instance = Instance::all()->first();
        $tournament = Competition::where('type', 'tournament')->where('groups', 0)->first();
        $tournamentGroup = Competition::where('type', 'tournament')->where('groups', 1)->first();
        $season = Season::where('instance_id', $instance->id)->firstOrFail();

        $this->assertSame(
            Carbon::create((int) date('Y'), 7, 1)->toDateString(),
            $instance->instance_date
        );

        $this->assertDatabaseHas(
            'instances',
            [
                'id' => $instance->id,
                'club_id' => $this->clubId,
                'user_id' => $this->userId,
                'manager_id' => $this->managerId,
            ]
        );

        $this->assertNotNull(
            Game::where('instance_id', $instance->id)
                ->where('season_id', $season->id)
                ->first()
        );

        $this->assertDatabaseHas(
            'games',
            [
                'instance_id' => $instance->id,
                'competition_id' => $tournament->id,
                'season_id' => $season->id,
            ]
        );

        $this->assertDatabaseHas(
            'tournament_knockout',
            [
                'instance_id' => $instance->id,
                'season_id' => $season->id,
                'competition_id' => $tournament->id,
            ]
        );

        $this->assertDatabaseHas(
            'competition_season',
            [
                'instance_id' => $instance->id,
                'season_id' => $season->id,
                'competition_id' => $tournamentGroup->id,
            ]
        );

        $leagueCompetitionIdsWithClubs = DB::table('competition_season AS cs')
            ->join('competitions AS c', 'c.id', '=', 'cs.competition_id')
            ->where('cs.instance_id', $instance->id)
            ->where('cs.season_id', $season->id)
            ->where('c.type', 'league')
            ->groupBy('cs.competition_id')
            ->pluck('cs.competition_id');

        $this->assertNotEmpty($leagueCompetitionIdsWithClubs);

        $championship = Competition::query()
            ->where('instance_id', $instance->id)
            ->where('base_competition_id', 8)
            ->firstOrFail();
        $this->assertSame(
            20,
            DB::table('competition_season')
                ->where('instance_id', $instance->id)
                ->where('season_id', $season->id)
                ->where('competition_id', $championship->id)
                ->count()
        );

        foreach ($leagueCompetitionIdsWithClubs as $competitionId) {
            $clubCount = DB::table('competition_season')
                ->where('instance_id', $instance->id)
                ->where('season_id', $season->id)
                ->where('competition_id', $competitionId)
                ->count();

            $this->assertEquals(
                $clubCount * ($clubCount - 1),
                Game::where('instance_id', $instance->id)
                    ->where('season_id', $season->id)
                    ->where('competition_id', $competitionId)
                    ->count()
            );
        }

        $leagueCompetitionIdsWithoutClubs = Competition::query()
            ->where('instance_id', $instance->id)
            ->where('type', 'league')
            ->whereNotIn('id', $leagueCompetitionIdsWithClubs)
            ->pluck('id');

        foreach ($leagueCompetitionIdsWithoutClubs as $competitionId) {
            $this->assertEquals(
                0,
                Game::where('instance_id', $instance->id)
                    ->where('season_id', $season->id)
                    ->where('competition_id', $competitionId)
                    ->count()
            );
        }

        $continentalCompetitions = Competition::query()
            ->where('instance_id', $instance->id)
            ->where('competition_scope', 'continental')
            ->get();

        foreach ($continentalCompetitions as $continentalCompetition) {
            $memberships = DB::table('competition_season')
                ->where('competition_season.instance_id', $instance->id)
                ->where('competition_season.season_id', $season->id)
                ->where('competition_season.competition_id', $continentalCompetition->id);

            $this->assertSame($continentalCompetition->clubs_number, $memberships->count());
            $this->assertSame(
                5,
                (clone $memberships)
                    ->join('clubs', 'clubs.id', '=', 'competition_season.club_id')
                    ->distinct()
                    ->count('clubs.country_code')
            );
        }

        $continentalMemberships = DB::table('competition_season AS cs')
            ->join('competitions AS competition', 'competition.id', '=', 'cs.competition_id')
            ->where('cs.instance_id', $instance->id)
            ->where('cs.season_id', $season->id)
            ->where('competition.competition_scope', 'continental');

        $this->assertSame(96, (clone $continentalMemberships)->count());
        $this->assertSame(96, (clone $continentalMemberships)->distinct()->count('cs.club_id'));
        $this->assertDatabaseHas(
            'seasons',
            [
                'id' => $season->id,
                'start_date' => $season->start_date,
                'end_date' => $season->end_date,
            ]
        );

        $club = Club::all()->first();
        $players = Player::where('instance_id', $instance->id)->where('club_id', $club->id)->get();

        // atm each club should have 36 players assigned when creating a game
        $this->assertEquals(36, $players->count());
        $this->assertSame($players->count(), $players->whereNotNull('person_id')->count());
        $this->assertSame($players->count(), $players->pluck('person_id')->unique()->count());
        $this->assertNotNull($players->first()->person);
        $this->assertNotEmpty($players->first()->first_name);

        $coachingStaff = StaffCoaching::where('club_id', $club->id)->get();
        $physios = StaffPhysio::where('club_id', $club->id)->get();
        $scouts = StaffScout::where('club_id', $club->id)->get();

        $this->assertCount(11, $coachingStaff);
        $this->assertCount(1, $coachingStaff->where('type', 'MANAGER'));
        $this->assertCount(1, $coachingStaff->where('type', 'ASSISTANT_MANAGER'));
        $this->assertCount(6, $coachingStaff->where('type', 'COACH'));
        $this->assertCount(3, $coachingStaff->where('type', 'YOUTH_COACH'));
        $this->assertCount(5, $scouts);
        $this->assertCount(3, $physios->where('team_type', 'FIRST_TEAM'));
        $this->assertCount(1, $physios->where('team_type', 'YOUTH_TEAM'));
        $this->assertSame(20, $coachingStaff->pluck('person_id')->merge($physios->pluck('person_id'))
            ->merge($scouts->pluck('person_id'))->unique()->count());
    }

    protected function getNewInstance(): CreateInstance
    {
        $this->competitionDataSource = new CompetitionDataSource;
        $this->competitionRepository = new CompetitionRepository($this->competitionDataSource);
        $this->competitionService = new CompetitionService(
            (new LeagueUpdater($this->competitionRepository)),
            (new TournamentUpdater($this->competitionRepository)),
            $this->competitionDataSource
        );
        $this->personService = new PersonService;

        return
            new CreateInstance(
                app()->make(CompetitionService::class),
                app()->make(PersonService::class),
                app()->make(CompetitionRepository::class),
                app()->make(PlayerPotential::class),
                app()->make(StaffGenerator::class),
                app()->make(PlayerRepository::class),
                app()->make(StaffRepository::class)
            );
    }
}
