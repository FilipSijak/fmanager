<?php

namespace Tests\Integration\Instance;

use App\Events\NextDay;
use App\Models\Club;
use App\Models\Competition;
use App\Models\Instance;
use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InstanceAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['cache.default' => 'array']);
    }

    #[Test]
    public function guests_cannot_access_game_apis_even_with_a_valid_hash(): void
    {
        $instance = Instance::factory()->create();
        $this->withHeader('instanceHash', $instance->instance_hash);
        $this->getJson('/api/dashboard')->assertUnauthorized();
        $this->getJson('/api/club/1')->assertUnauthorized();
        $this->getJson('/api/player/1')->assertUnauthorized();
        $this->getJson('/api/competition/1/table')->assertUnauthorized();
        $this->postJson('/api/startNewGame')->assertUnauthorized();
        $this->postJson('/api/instance/next-day')->assertUnauthorized();
        $this->postJson('/api/transfer')->assertUnauthorized();
        $this->postJson('/api/news/1/read')->assertUnauthorized();
        $this->postJson('/api/game/1/complete')->assertUnauthorized();
    }

    #[Test]
    public function users_can_list_and_switch_between_their_own_games(): void
    {
        $user = User::factory()->create();
        $club = Club::factory()->create();
        $games = Instance::factory()->count(2)->create(['user_id' => $user->id, 'club_id' => $club->id]);
        $other = Instance::factory()->create();
        $this->actingAs($user)->get('/setup-game')->assertInertia(fn (Assert $page) => $page
            ->component('GameStart')->has('instances', 2)
            ->where('instances.0.id', $games[1]->id)
            ->where('instances.1.id', $games[0]->id)
            ->missing('instances.0.instance_hash'));

        foreach ($games as $game) {
            $this->post("/instances/{$game->id}/select")
                ->assertRedirect('/')->assertSessionHas('active_instance_hash', $game->instance_hash);
            $this->get('/')->assertInertia(fn (Assert $page) => $page->component('Dashboard'));
            $this->withHeader('Origin', 'http://localhost')->getJson('/api/news')->assertOk();
        }

        $this->post("/instances/{$other->id}/select")->assertNotFound();
        $this->assertSame($games[1]->instance_hash, session('active_instance_hash'));
    }

    #[Test]
    public function setup_lists_leagues_with_their_own_clubs(): void
    {
        $user = User::factory()->create();
        $instance = $this->createPendingWorld($user);

        $this->actingAs($user)->get('/setup-game?instance_id='.$instance->id)
            ->assertInertia(fn (Assert $page) => $page->component('GameStart')
                ->where('pending_instance_id', $instance->id)
                ->where('leagues', function ($leagues): bool {
                    $premierLeague = collect($leagues)->firstWhere('id', 1);
                    $bundesliga = collect($leagues)->firstWhere('id', 2);

                    return $premierLeague['name'] === 'Premier League'
                        && count($premierLeague['clubs']) === 1
                        && collect($premierLeague['clubs'])->contains('name', 'Arsenal')
                        && ! collect($bundesliga['clubs'])->contains('name', 'Arsenal')
                        && ! collect($leagues)->contains('id', 6);
                }));
    }

    #[Test]
    #[DataProvider('invalidGameSelections')]
    public function invalid_selections_leave_the_created_world_awaiting_a_club(array $selection, array $errors): void
    {
        $user = User::factory()->create();
        $instance = $this->createPendingWorld($user);
        $instanceCount = Instance::query()->count();

        $this->actingAs($user)
            ->withHeader('Origin', 'http://localhost')
            ->postJson('/instances/'.$instance->id.'/take-over', $selection)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($errors)
            ->assertSessionMissing('active_instance_hash');

        $this->assertDatabaseCount('instances', $instanceCount);
        $this->assertDatabaseHas('instances', ['id' => $instance->id, 'club_id' => null, 'manager_id' => null]);
        $this->assertDatabaseCount('staff_coaching', 0);
    }

    public static function invalidGameSelections(): array
    {
        return [
            'missing selection' => [[], ['league_id' => 'Choose a league.', 'club_id' => 'Choose a club.']],
            'unknown league' => [['league_id' => 99999, 'club_id' => 1], ['league_id' => 'Choose a valid league.']],
            'tournament' => [['league_id' => 6, 'club_id' => 1], ['league_id' => 'Choose a valid league.']],
            'club from another league' => [['league_id' => 2, 'club_id' => 1], ['club_id' => 'Choose a club from the selected league.']],
            'unknown club' => [['league_id' => 1, 'club_id' => 99999], ['club_id' => 'Choose a club from the selected league.']],
            'invalid league value' => [['league_id' => 'invalid', 'club_id' => 1], ['league_id' => 'Choose a valid league.']],
            'invalid club value' => [['league_id' => 1, 'club_id' => 'invalid'], ['club_id' => 'Choose a club from the selected league.']],
        ];
    }

    #[Test]
    public function pending_games_resume_club_selection_and_cannot_access_game_apis(): void
    {
        $user = User::factory()->create();
        $instance = $this->createPendingWorld($user);

        $this->actingAs($user)->post('/instances/'.$instance->id.'/select')
            ->assertRedirect('/setup-game?instance_id='.$instance->id)
            ->assertSessionMissing('active_instance_hash');
        $this->withHeader('instanceHash', $instance->instance_hash)->getJson('/api/dashboard')
            ->assertUnprocessable()->assertJsonPath('message', 'Choose a club to finish setting up this game.');
    }

    #[Test]
    public function users_cannot_choose_a_club_for_another_users_game(): void
    {
        $owner = User::factory()->create();
        $instance = $this->createPendingWorld($owner);

        $this->actingAs(User::factory()->create())->get('/setup-game?instance_id='.$instance->id)->assertNotFound();
        $this->postJson('/instances/'.$instance->id.'/take-over', ['league_id' => 1, 'club_id' => 1])->assertNotFound();
        $this->assertDatabaseHas('instances', ['id' => $instance->id, 'club_id' => null, 'manager_id' => null]);
    }

    #[Test]
    public function clubs_and_leagues_from_another_instance_cannot_be_selected(): void
    {
        $user = User::factory()->create();
        $instance = $this->createPendingWorld($user);
        $otherLeague = Competition::factory()->create(['instance_id' => 999]);
        $otherClub = Club::factory()->create(['instance_id' => 999]);

        $this->actingAs($user)->postJson('/instances/'.$instance->id.'/take-over', ['league_id' => $otherLeague->id, 'club_id' => $otherClub->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['league_id', 'club_id']);
        $this->assertDatabaseHas('instances', ['id' => $instance->id, 'club_id' => null, 'manager_id' => null]);
    }

    #[Test]
    public function guests_cannot_take_over_a_club(): void
    {
        $instance = $this->createPendingWorld(User::factory()->create());

        $this->postJson('/instances/'.$instance->id.'/take-over', ['league_id' => 1, 'club_id' => 1])->assertUnauthorized();
    }

    private function createPendingWorld(User $user): Instance
    {
        $instance = Instance::factory()->create(['id' => 100, 'user_id' => $user->id, 'club_id' => null, 'manager_id' => null]);
        $season = Season::factory()->create(['instance_id' => $instance->id]);
        $instance->season_id = $season->id;
        $instance->save();
        Club::factory()->create(['id' => 1, 'instance_id' => $instance->id, 'name' => 'Arsenal']);
        Club::factory()->create(['id' => 2, 'instance_id' => $instance->id, 'name' => 'Bayern']);
        Competition::factory()->create(['id' => 1, 'instance_id' => $instance->id, 'name' => 'Premier League']);
        Competition::factory()->create(['id' => 2, 'instance_id' => $instance->id, 'name' => 'Bundesliga']);
        Competition::factory()->create(['id' => 6, 'instance_id' => $instance->id, 'type' => 'tournament']);

        foreach ([[1, 1], [2, 2], [6, 1]] as [$competitionId, $clubId]) {
            DB::table('competition_season')->insert([
                'instance_id' => $instance->id, 'season_id' => $season->id,
                'competition_id' => $competitionId, 'club_id' => $clubId,
            ]);
        }

        return $instance;
    }

    #[Test]
    public function another_users_hash_cannot_be_used_for_reads_or_writes(): void
    {
        $user = User::factory()->create();
        $other = Instance::factory()->create(['instance_date' => '2024-03-02']);
        $this->actingAs($user)->withHeader('instanceHash', $other->instance_hash);
        $this->getJson('/api/dashboard')->assertNotFound();
        $this->postJson('/api/instance/next-day')->assertNotFound();
        $this->assertSame('2024-03-02', $other->fresh()->instance_date);
    }

    #[Test]
    public function authenticated_users_must_select_a_game(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertRedirect('/setup-game');
        $this->getJson('/api/dashboard')->assertUnprocessable()->assertJsonPath('message', 'Select a game first.');
        $this->withHeader('instanceHash', 'missing')->getJson('/api/dashboard')->assertNotFound();
    }

    #[Test]
    public function time_advances_only_for_the_selected_owned_game(): void
    {
        $user = User::factory()->create();
        $other = Instance::factory()->create(['instance_date' => '2024-03-02']);
        $instance = Instance::factory()->create(['user_id' => $user->id, 'instance_date' => '2024-03-02']);
        $season = Season::factory()->create(['instance_id' => $instance->id]);
        $instance->season_id = $season->id;
        $instance->save();
        Event::fake([NextDay::class]);

        $this->actingAs($user)->withHeader('instanceHash', $instance->instance_hash)
            ->postJson('/api/instance/next-day')->assertOk()->assertJsonPath('data.date', '2024-03-03');

        $this->assertSame('2024-03-03', $instance->fresh()->instance_date);
        $this->assertSame('2024-03-02', $other->fresh()->instance_date);
        Event::assertDispatched(NextDay::class, fn (NextDay $event) => $event->instance->is($instance));
        $this->getJson('/api/instance/next-day')->assertStatus(405);
    }
}
