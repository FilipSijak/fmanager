<?php

namespace Tests\Integration\Instance;

use App\Events\NextDay;
use App\Models\Instance;
use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
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
        $games = Instance::factory()->count(2)->create(['user_id' => $user->id]);
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
            ->postJson('/api/instance/next-day')->assertOk();

        $this->assertSame('2024-03-03', $instance->fresh()->instance_date);
        $this->assertSame('2024-03-02', $other->fresh()->instance_date);
        Event::assertDispatched(NextDay::class, fn (NextDay $event) => $event->instance->is($instance));
        $this->getJson('/api/instance/next-day')->assertStatus(405);
    }
}
