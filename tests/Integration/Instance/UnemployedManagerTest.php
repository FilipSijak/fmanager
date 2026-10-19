<?php

namespace Tests\Integration\Instance;

use App\Models\Club;
use App\Models\Competition;
use App\Models\Instance;
use App\Models\Manager;
use App\Models\News;
use App\Models\StaffCoaching;
use App\Models\StaffContract;
use App\Models\User;
use App\Services\NewsService\NewsType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UnemployedManagerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Instance $instance;

    private Club $club;

    private Competition $league;

    private StaffCoaching $userManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->user = User::factory()->create();
        $this->instance = Instance::factory()->create(['user_id' => $this->user->id, 'season_id' => 1, 'manager_id' => null]);
        $this->club = Club::factory()->create(['instance_id' => $this->instance->id, 'name' => 'Left FC']);
        $this->league = Competition::factory()->create(['instance_id' => $this->instance->id, 'name' => 'Followed League']);
        $contract = StaffContract::query()->create(['contract_start' => '2026-07-01', 'contract_end' => '2028-06-30', 'salary' => 10000]);
        $this->userManager = StaffCoaching::factory()->create([
            'instance_id' => $this->instance->id,
            'club_id' => $this->club->id,
            'contract_id' => $contract->id,
        ]);
        $this->instance->club_id = $this->club->id;
        $this->instance->manager_id = $this->userManager->id;
        $this->instance->followed_competition_id = $this->league->id;
        $this->instance->save();
        Manager::factory()->create([
            'user_id' => $this->user->id,
            'instance_id' => $this->instance->id,
            'staff_coaching_id' => $this->userManager->id,
        ]);

        $this->actingAs($this->user)
            ->withSession(['active_instance_hash' => $this->instance->instance_hash])
            ->withHeader('instanceHash', $this->instance->instance_hash);
    }

    #[Test]
    public function resigning_hands_the_club_and_contract_to_the_best_free_manager(): void
    {
        $this->freeManager(['coaching_potential' => 90]);
        $bestFreeManager = $this->freeManager(['coaching_potential' => 150]);
        $contractId = $this->userManager->contract_id;

        $this->postJson('/api/instance/resign')->assertOk();

        $this->assertDatabaseHas('instances', [
            'id' => $this->instance->id,
            'club_id' => null,
            'manager_id' => $this->userManager->id,
            'followed_competition_id' => $this->league->id,
        ]);
        $this->assertDatabaseHas('staff_coaching', ['id' => $this->userManager->id, 'club_id' => null, 'contract_id' => null, 'is_retired' => false]);
        $this->assertDatabaseHas('staff_coaching', ['id' => $bestFreeManager->id, 'club_id' => $this->club->id, 'contract_id' => $contractId]);
        $this->assertDatabaseHas('managers', ['instance_id' => $this->instance->id, 'staff_coaching_id' => $this->userManager->id]);
        $this->postJson('/api/instance/resign')->assertForbidden();
    }

    #[Test]
    public function resigning_is_refused_when_no_free_manager_can_take_over(): void
    {
        $this->postJson('/api/instance/resign')
            ->assertConflict()
            ->assertJsonPath('message', 'No free manager is available to take over the club.');

        $this->assertDatabaseHas('instances', ['id' => $this->instance->id, 'club_id' => $this->club->id]);
        $this->assertDatabaseHas('staff_coaching', ['id' => $this->userManager->id, 'club_id' => $this->club->id]);
    }

    #[Test]
    public function unemployed_managers_follow_their_league_without_club_management(): void
    {
        $this->freeManager();
        $this->postJson('/api/instance/resign')->assertOk();
        $this->news(['title' => 'League news', 'competition_id' => $this->league->id]);
        $this->news(['title' => 'Former club news', 'club_id' => $this->club->id]);
        $this->news(['title' => 'Other league news', 'competition_id' => Competition::factory()->create(['instance_id' => $this->instance->id])->id]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('game.has_club', false)
            ->where('dashboard.club', null)
            ->where('dashboard.followed_competition.name', 'Followed League')
            ->where('dashboard.news', fn ($news): bool => collect($news)->pluck('title')->all() === ['League news']));
        $this->getJson('/api/finance')->assertForbidden();
        $this->getJson('/api/squad/lineup')->assertForbidden();
        $this->get('/squad')->assertRedirect('/');
        $this->post('/instances/'.$this->instance->id.'/select')->assertRedirect('/');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function freeManager(array $attributes = []): StaffCoaching
    {
        return StaffCoaching::factory()->create(array_merge([
            'instance_id' => $this->instance->id,
            'club_id' => null,
            'contract_id' => null,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function news(array $attributes): void
    {
        News::create(array_merge([
            'instance_id' => $this->instance->id, 'season_id' => 1, 'club_id' => null, 'competition_id' => null,
            'content' => 'Content.', 'type' => NewsType::Transfer->value, 'priority' => 1,
            'published_at' => now(), 'is_read' => false, 'read_at' => null,
        ], $attributes));
    }
}
