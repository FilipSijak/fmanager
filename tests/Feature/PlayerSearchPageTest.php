<?php

namespace Tests\Feature;

use App\Models\Instance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlayerSearchPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    #[Test]
    public function guests_must_sign_in_to_find_players(): void
    {
        $this->get('/find')->assertRedirect('/login');
    }

    #[Test]
    public function it_renders_the_player_search_screen_for_the_selected_game(): void
    {
        $instance = Instance::factory()->create();

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->get('/find')->assertInertia(fn (Assert $page) => $page
            ->component('player-search/PlayerSearch')
            ->where('auth.user.id', $instance->user_id)->where('game.has_club', true));
    }

    #[Test]
    public function unemployed_managers_can_find_players(): void
    {
        $instance = Instance::factory()->create(['club_id' => null]);

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->get('/find')->assertInertia(fn (Assert $page) => $page
            ->component('player-search/PlayerSearch')->where('game.has_club', false));
    }

    #[Test]
    public function users_must_select_a_game_before_searching(): void
    {
        $this->actingAs(User::factory()->create())->get('/find')->assertRedirect('/setup-game');
    }

    #[Test]
    public function another_users_game_cannot_be_selected_for_search(): void
    {
        $instance = Instance::factory()->create();
        $otherInstance = Instance::factory()->create();

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $otherInstance->instance_hash])
            ->get('/find')->assertNotFound();
    }
}
