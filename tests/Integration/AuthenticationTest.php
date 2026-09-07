<?php

namespace Tests\Integration;

use App\Models\Instance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['cache.default' => 'array']);
    }

    #[Test]
    public function guests_can_open_auth_forms_but_not_game_pages(): void
    {
        $this->get('/login')->assertInertia(fn (Assert $page) => $page->component('Auth')->where('registering', false));
        $this->get('/register')->assertInertia(fn (Assert $page) => $page->component('Auth')->where('registering', true));
        foreach (['/', '/setup-game', '/squad', '/player-profile', '/league-table'] as $path) {
            $this->get($path)->assertRedirect('/login');
        }
    }

    #[Test]
    public function registration_creates_a_user_and_starts_a_session_without_issuing_tokens(): void
    {
        $this->post('/register', [
            'name' => 'New Manager', 'email' => 'manager@example.com',
            'password' => 'abcde', 'password_confirmation' => 'abcde',
        ])->assertRedirect('/setup-game');

        $user = User::where('email', 'manager@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertTrue(Hash::check('abcde', $user->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[Test]
    public function registration_rejects_passwords_shorter_than_five_characters(): void
    {
        $this->postJson('/register', [
            'name' => 'New Manager', 'email' => 'manager@example.com',
            'password' => 'abcd', 'password_confirmation' => 'abcd',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    #[Test]
    public function registration_rejects_duplicate_email_and_unconfirmed_password(): void
    {
        $user = User::factory()->create();
        $this->postJson('/register', [
            'name' => 'Manager', 'email' => $user->email,
            'password' => 'secure-password', 'password_confirmation' => 'different-password',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
        $this->assertDatabaseCount('users', 1);
        $this->assertGuest();
    }

    #[Test]
    public function login_rejects_invalid_credentials_and_throttles_attempts(): void
    {
        $user = User::factory()->create();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong'])
                ->assertUnprocessable()->assertJsonValidationErrors('email');
        }
        $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong'])->assertStatus(429);
        $this->assertGuest();
    }

    #[Test]
    public function session_login_authenticates_sanctum_requests_and_logout_revokes_the_session(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secure-password')]);
        $instance = Instance::factory()->create(['user_id' => $user->id, 'instance_hash' => 'owned-game']);
        $this->withSession(['active_instance_hash' => 'old-game']);
        $oldSessionId = session()->getId();
        $response = $this->post('/login', ['email' => $user->email, 'password' => 'secure-password'])
            ->assertRedirect('/setup-game')->assertSessionMissing('active_instance_hash');
        $this->assertNotSame($oldSessionId, session()->getId());
        $sessionId = $response->getCookie(config('session.cookie'))->getValue();
        $this->assertDatabaseCount('personal_access_tokens', 0);

        Auth::forgetGuards();
        $this->withCookie(config('session.cookie'), $sessionId)
            ->withHeader('Origin', 'http://localhost')
            ->getJson('/api/news', ['instanceHash' => $instance->instance_hash])
            ->assertOk();

        $this->post('/logout')->assertRedirect('/login')->assertSessionMissing('active_instance_hash');
        Auth::forgetGuards();
        $this->getJson('/api/news', ['instanceHash' => $instance->instance_hash])->assertUnauthorized();
    }

    #[Test]
    public function web_and_stateful_api_mutations_require_csrf_tokens(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->app['env'] = 'local';
        $this->postJson('/logout')->assertStatus(419);
        $this->withHeader('Origin', 'http://localhost')->postJson('/api/startNewGame')->assertStatus(419);
        $this->assertDatabaseCount('instances', 0);
    }
}
