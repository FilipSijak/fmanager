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
use App\Models\StaffContract;
use App\Models\StaffPhysio;
use App\Models\StaffScout;
use App\Models\User;
use App\Repositories\Competition\CompetitionSeasonRepository;
use App\Repositories\Competition\CompetitionStandingsRepository;
use App\Repositories\Competition\CompetitionTournamentRepository;
use App\Services\CompetitionService\Competitions\LeagueUpdater;
use App\Services\CompetitionService\Competitions\TournamentUpdater;
use App\Services\CompetitionService\CompetitionService;
use App\Services\CompetitionService\DataLayer\CompetitionDataSource;
use App\Services\InstanceService\CreateInstance;
use App\Services\PersonService\PersonService;
use App\Services\StadiumService\StadiumType;
use App\Services\TacticsService\TacticsService;
use App\Support\GameContext;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TacticsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreateInstanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create(['id' => 99]);
        $this->userId = $user->id;
        $this->actingAs($user);
    }

    #[Test]
    public function it_can_setup_a_new_game()
    {
        $createInstance = $this->getNewInstance();
        (new DatabaseSeeder)->run();
        Instance::factory()->create(['id' => 1, 'user_id' => $this->userId]);
        Season::factory()->create(['id' => 1, 'instance_id' => 1]);
        $response = $this->withHeader('Origin', 'http://localhost')->postJson('/api/startNewGame', ['user_id' => 1])->assertCreated();
        $response->assertSessionMissing('active_instance_hash');
        $this->assertSame(2, User::findOrFail($this->userId)->instances()->count());
        $instance = Instance::findOrFail($response->json('data.id'));
        $this->assertNotSame(1, $instance->id);
        $managedClub = Club::query()->where('instance_id', $instance->id)->where('base_club_id', 2)->firstOrFail();
        $this->assertNull($instance->club_id);
        $this->assertNull($instance->manager_id);
        $this->assertSame(200, Player::query()->forInstance($instance->id)->whereNull('club_id')->count());
        $this->assertNotNull(Game::query()->where('instance_id', $instance->id)->first());
        $previousManager = StaffCoaching::query()->where('club_id', $managedClub->id)->where('type', 'MANAGER')->firstOrFail();
        $this->withoutVite();
        $this->get('/setup-game?instance_id='.$instance->id)->assertInertia(fn (Assert $page) => $page
            ->component('GameStart')->where('pending_instance_id', $instance->id)
            ->where('leagues', function ($leagues) use ($managedClub): bool {
                $league = collect($leagues)->firstWhere('name', 'Premier League');

                return count($league['clubs']) === 20 && collect($league['clubs'])->contains('id', $managedClub->id)
                    && ! collect($leagues)->contains('name', 'Champions League');
            }));
        $league = Competition::query()->forInstance($instance->id)->where('base_competition_id', 1)->firstOrFail();
        $this->postJson('/instances/'.$instance->id.'/take-over', ['league_id' => $league->id, 'club_id' => $managedClub->id])
            ->assertOk()->assertSessionHas('active_instance_hash', $instance->instance_hash);
        $instance->refresh();
        $this->assertSame($league->id, $instance->followed_competition_id);
        $this->assertDatabaseHas('staff_coaching', ['id' => $previousManager->id, 'club_id' => null, 'contract_id' => null]);
        $this->postJson('/instances/'.$instance->id.'/take-over', ['league_id' => $league->id, 'club_id' => $managedClub->id])
            ->assertConflict();
        $manager = StaffCoaching::query()->findOrFail($instance->manager_id);
        $this->assertDatabaseHas('managers', [
            'user_id' => $this->userId,
            'instance_id' => $instance->id,
            'staff_coaching_id' => $manager->id,
            'reputation' => 50,
        ]);
        $this->assertSame(1, Manager::query()->where('instance_id', $instance->id)->count());
        $this->assertDatabaseHas('staff_coaching', [
            'id' => $manager->id,
            'instance_id' => $instance->id,
            'club_id' => $managedClub->id,
            'type' => 'MANAGER',
            'attacking' => 1,
            'defending' => 1,
            'fitness' => 1,
            'mental' => 1,
            'tactical' => 1,
            'technical' => 1,
            'working_with_youngsters' => 1,
            'adaptability' => 1,
            'determination' => 1,
            'discipline' => 1,
            'man_management' => 1,
            'motivating' => 1,
            'judging_player_potential' => 1,
            'judging_player_ability' => 1,
            'judging_staff_ability' => 1,
            'negotiating' => 1,
            'tactics' => 1,
            'distribution' => 1,
            'handling' => 1,
            'shot_stopping' => 1,
        ]);
        $this->assertSame(
            User::findOrFail($this->userId)->name,
            trim($manager->person->first_name.' '.$manager->person->last_name)
        );
        $this->assertSame(StadiumType::GLOBAL, $managedClub->stadium->type);
        $this->assertSame($instance->instance_hash, $response->json('data.instance_hash'));
        $tournament = Competition::where('type', 'tournament')->where('groups', 0)->first();
        $tournamentGroup = Competition::where('type', 'tournament')->where('groups', 1)->first();
        $season = Season::where('instance_id', $instance->id)->firstOrFail();

        $this->assertSame($season->id, $instance->season_id);

        $this->assertSame(
            Carbon::create((int) date('Y'), 7, 1)->toDateString(),
            $instance->instance_date
        );

        $this->assertDatabaseHas(
            'instances',
            [
                'id' => $instance->id,
                'club_id' => $managedClub->id,
                'user_id' => $this->userId,
                'manager_id' => $manager->id,
            ]
        );

        $this->assertNotNull(
            Game::where('instance_id', $instance->id)
                ->where('season_id', $season->id)
                ->first()
        );

        $clubStadiumIds = DB::table('clubs')
            ->where('instance_id', $instance->id)
            ->distinct()
            ->pluck('stadium_id');
        $venueCounts = DB::table('stadium_commercial_venues')
            ->where('instance_id', $instance->id)
            ->select('stadium_id', DB::raw('COUNT(*) AS venue_count'))
            ->groupBy('stadium_id')
            ->pluck('venue_count', 'stadium_id');

        $this->assertCount($clubStadiumIds->count(), $venueCounts);

        foreach (DB::table('stadiums')->where('instance_id', $instance->id)->whereIn('id', $clubStadiumIds)->get() as $stadium) {
            $allowedVenueCount = DB::table('base_commercial_category_stadium_type')
                ->where('stadium_type', $stadium->type)
                ->count();
            $expectedVenueCount = min(intdiv((int) $stadium->commercial_limit, 2), $allowedVenueCount);

            $this->assertSame($expectedVenueCount, (int) $venueCounts->get($stadium->id));
        }

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

        $goalkeepers = $players->where('position', 'GK');
        $this->assertCount(3, $goalkeepers);
        $this->assertSame(
            3,
            DB::table('player_position')
                ->whereIn('player_id', $goalkeepers->pluck('id'))
                ->where('position_id', 15)
                ->count()
        );
        $this->assertTrue($goalkeepers->every(fn (Player $player): bool => $player->handling > 1 && $player->reflexes > 1));
        $this->assertSame(200, Player::where('instance_id', $instance->id)->whereNull('club_id')->count());

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
        $this->assertSame(11, $coachingStaff->whereNotNull('contract_id')->count());
        $this->assertSame(4, $physios->whereNotNull('contract_id')->count());
        $this->assertSame(5, $scouts->whereNotNull('contract_id')->count());

        $clubCount = Club::where('instance_id', $instance->id)->count();
        $assignedStaffCount = StaffCoaching::where('instance_id', $instance->id)->whereNotNull('club_id')->count()
            + StaffPhysio::where('instance_id', $instance->id)->whereNotNull('club_id')->count()
            + StaffScout::where('instance_id', $instance->id)->whereNotNull('club_id')->count();
        $freeStaffCount = StaffCoaching::where('instance_id', $instance->id)->whereNull('club_id')->count()
            + StaffPhysio::where('instance_id', $instance->id)->whereNull('club_id')->count()
            + StaffScout::where('instance_id', $instance->id)->whereNull('club_id')->count();

        $this->assertSame($clubCount * 20, $assignedStaffCount);
        $this->assertSame($clubCount * 5 + 1, $freeStaffCount);
        $this->assertSame($freeStaffCount, StaffCoaching::whereNull('club_id')->whereNull('contract_id')->count()
            + StaffPhysio::whereNull('club_id')->whereNull('contract_id')->count()
            + StaffScout::whereNull('club_id')->whereNull('contract_id')->count());
    }

    #[Test]
    public function taking_over_a_club_preserves_its_previous_manager_as_free_staff(): void
    {
        $this->seed(TacticsSeeder::class);
        $club = Club::factory()->create(['instance_id' => 1]);
        $contract = StaffContract::query()->create([
            'contract_start' => '2026-07-01',
            'contract_end' => '2027-07-01',
            'salary' => 5000,
        ]);
        $previousManager = StaffCoaching::factory()->create([
            'instance_id' => $club->instance_id,
            'club_id' => $club->id,
            'contract_id' => $contract->id,
            'attacking' => 15,
            'coaching_potential' => 160,
        ]);
        $previousPerson = $previousManager->person->getAttributes();
        $previousAttributes = $previousManager->getAttributes();
        $preference = app(TacticsService::class)->ensureDefaultForStaff($previousManager);
        $user = User::findOrFail($this->userId);

        $manager = app(PersonService::class)->assignUserManager($club, $user);

        $this->assertDatabaseHas('staff_coaching', array_merge($previousAttributes, [
            'club_id' => null,
            'contract_id' => null,
            'is_retired' => false,
        ]));
        $this->assertDatabaseHas('people', $previousPerson);
        $this->assertNotSame($previousManager->id, $manager->id);
        $this->assertNotSame($previousManager->person_id, $manager->person_id);
        $this->assertDatabaseHas('staff_coaching', [
            'id' => $manager->id,
            'instance_id' => $club->instance_id,
            'club_id' => $club->id,
            'type' => 'MANAGER',
            'contract_id' => $contract->id,
            'attacking' => 1,
        ]);
        $this->assertSame($user->name, trim($manager->person->first_name.' '.$manager->person->last_name));
        $this->assertSame(1, StaffCoaching::query()->where('club_id', $club->id)->where('type', 'MANAGER')->count());
        $this->assertSame($preference->base_formation_id, $manager->tacticPreference->base_formation_id);
        $this->assertModelExists($preference);
    }

    protected function getNewInstance(): CreateInstance
    {
        $this->competitionDataSource = new CompetitionDataSource;
        $standings = app(CompetitionStandingsRepository::class);
        $this->competitionService = new CompetitionService(
            (new LeagueUpdater($standings)),
            (new TournamentUpdater($standings, app(CompetitionTournamentRepository::class), app(GameContext::class))),
            $this->competitionDataSource
        );
        $this->personService = app()->make(PersonService::class);

        return
            new CreateInstance(
                app()->make(CompetitionService::class),
                app()->make(PersonService::class),
                app()->make(CompetitionSeasonRepository::class)
            );
    }
}
