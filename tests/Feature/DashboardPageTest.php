<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Club;
use App\Models\Instance;
use App\Models\Manager;
use App\Models\News;
use App\Models\Person;
use App\Models\StaffCoaching;
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
    public function it_defaults_to_the_users_latest_managed_game_with_its_manager(): void
    {
        $olderGame = Manager::factory()->create();
        $user = $olderGame->user;
        $club = Club::factory()->create(['name' => 'Latest FC']);
        $instance = Instance::factory()->create(['user_id' => $user->id, 'club_id' => $club->id, 'season_id' => 1]);
        $person = Person::factory()->create(['instance_id' => $instance->id, 'first_name' => 'Ada', 'last_name' => 'Boss']);
        $staff = StaffCoaching::factory()->create(['instance_id' => $instance->id, 'club_id' => $club->id, 'person_id' => $person->id]);
        $instance->manager_id = $staff->id;
        $instance->save();
        Manager::factory()->create(['user_id' => $user->id, 'instance_id' => $instance->id, 'staff_coaching_id' => $staff->id]);
        Instance::factory()->create(['user_id' => $user->id, 'club_id' => null, 'manager_id' => null]);

        $this->actingAs($user)
            ->get('/')
            ->assertSessionHas('active_instance_hash', $instance->instance_hash)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('dashboard.club.name', 'Latest FC')
                ->where('dashboard.manager', ['id' => $staff->id, 'name' => 'Ada Boss']));
    }
}
