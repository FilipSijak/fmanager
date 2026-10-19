<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Club;
use App\Models\Instance;
use App\Models\Manager;
use App\Models\News;
use App\Models\Person;
use App\Models\StaffCoaching;
use App\Models\User;
use App\Services\NewsService\NewsType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    #[Test]
    public function it_renders_dashboard_data_from_the_dashboard_service(): void
    {
        $club = Club::factory()->create(['name' => 'Managed FC']);
        $instance = Instance::factory()->create(['club_id' => $club->id, 'season_id' => 1]);
        Account::factory()->create(['club_id' => $club->id]);
        News::create([
            'instance_id' => $instance->id, 'season_id' => 1, 'club_id' => $club->id,
            'title' => 'Welcome news', 'content' => 'Welcome to the club.',
            'type' => NewsType::Transfer->value, 'priority' => 1, 'published_at' => now(),
            'is_read' => false, 'read_at' => null,
        ]);

        $this->actingAs($instance->user)
            ->withSession(['active_instance_hash' => $instance->instance_hash])
            ->get('/')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('dashboard.club.name', 'Managed FC')
                ->where('dashboard.news.0.title', 'Welcome news'));
    }

    #[Test]
    public function it_defaults_to_the_users_only_game_with_its_manager(): void
    {
        $user = User::factory()->create();
        $instance = $this->managedGame($user, 'Only FC', 'Ada', 'Boss');

        $this->actingAs($user)
            ->get('/')
            ->assertSessionHas('active_instance_hash', $instance->instance_hash)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('dashboard.club.name', 'Only FC')
                ->where('dashboard.manager', ['id' => $instance->manager_id, 'name' => 'Ada Boss']));
    }

    #[Test]
    public function users_with_several_games_choose_which_one_to_load(): void
    {
        $user = User::factory()->create();
        $this->managedGame($user, 'First FC', 'Ada', 'Boss');
        $this->managedGame($user, 'Second FC', 'Ada', 'Boss');

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect('/setup-game')
            ->assertSessionMissing('active_instance_hash');
    }

    private function managedGame(User $user, string $clubName, string $firstName, string $lastName): Instance
    {
        $club = Club::factory()->create(['name' => $clubName]);
        $instance = Instance::factory()->create(['user_id' => $user->id, 'club_id' => $club->id, 'season_id' => 1]);
        $person = Person::factory()->create(['instance_id' => $instance->id, 'first_name' => $firstName, 'last_name' => $lastName]);
        $staff = StaffCoaching::factory()->create(['instance_id' => $instance->id, 'club_id' => $club->id, 'person_id' => $person->id]);
        $instance->manager_id = $staff->id;
        $instance->save();
        Manager::factory()->create(['user_id' => $user->id, 'instance_id' => $instance->id, 'staff_coaching_id' => $staff->id]);

        return $instance;
    }
}
