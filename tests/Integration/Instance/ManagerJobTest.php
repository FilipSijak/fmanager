<?php

namespace Tests\Integration\Instance;

use App\Models\Club;
use App\Models\Competition;
use App\Models\Instance;
use App\Models\Manager;
use App\Models\ManagerJobApplication;
use App\Models\StaffCoaching;
use App\Models\User;
use App\Services\ManagerJobService\ManagerJobApplicationStatus;
use App\Services\ManagerJobService\ManagerJobService;
use App\Services\TacticsService\TacticsService;
use Database\Seeders\TacticsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ManagerJobTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Instance $instance;

    private Competition $league;

    private Club $vacantClub;

    private StaffCoaching $userManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->user = User::factory()->create();
        $this->instance = Instance::factory()->create([
            'user_id' => $this->user->id, 'season_id' => 1, 'club_id' => null, 'manager_id' => null, 'instance_date' => '2026-09-01',
        ]);
        $this->league = Competition::factory()->create(['instance_id' => $this->instance->id, 'name' => 'Vacancy League']);
        $this->vacantClub = $this->clubInLeague(['name' => 'Vacant FC', 'rank' => 10]);
        $this->userManager = $this->manager(['club_id' => null, 'reputation' => 95]);
        $this->instance->manager_id = $this->userManager->id;
        $this->instance->save();
        Manager::factory()->create(['user_id' => $this->user->id, 'instance_id' => $this->instance->id, 'staff_coaching_id' => $this->userManager->id]);

        $this->actingAs($this->user)->withSession(['active_instance_hash' => $this->instance->instance_hash]);
    }

    #[Test]
    public function unemployed_managers_see_vacancies_and_can_apply_once(): void
    {
        $this->clubInLeague(['name' => 'Managed FC', 'rank' => 12], withManager: true);

        $this->get('/jobs')->assertInertia(fn (Assert $page) => $page
            ->component('Jobs')
            ->where('is_unemployed', true)
            ->where('reputation', 95)
            ->where('vacancies', [[
                'club_id' => $this->vacantClub->id, 'club_name' => 'Vacant FC', 'league_name' => 'Vacancy League',
                'required_reputation' => 100, 'applied' => false,
            ]]));

        $this->from('/jobs')->post('/jobs/'.$this->vacantClub->id.'/apply')->assertRedirect('/jobs')->assertSessionHasNoErrors();
        $this->from('/jobs')->post('/jobs/'.$this->vacantClub->id.'/apply')
            ->assertSessionHasErrors(['application' => 'You have already applied for this job.']);
        $this->get('/jobs')->assertInertia(fn (Assert $page) => $page->where('vacancies.0.applied', true));
        $this->assertSame(1, ManagerJobApplication::query()->pending()->count());
    }

    #[Test]
    public function managers_cannot_apply_to_clubs_with_a_manager_or_while_employed(): void
    {
        $managedClub = $this->clubInLeague(['rank' => 10], withManager: true);

        $this->from('/jobs')->post('/jobs/'.$managedClub->id.'/apply')
            ->assertSessionHasErrors(['application' => 'This club is not looking for a manager.']);

        $this->instance->club_id = $managedClub->id;
        $this->instance->save();
        $this->from('/jobs')->post('/jobs/'.$this->vacantClub->id.'/apply')
            ->assertSessionHasErrors(['application' => 'Only managers without a club can apply for jobs.']);
        $this->assertSame(0, ManagerJobApplication::query()->count());
    }

    #[Test]
    public function the_board_appoints_the_applicant_closest_to_its_reputation_target(): void
    {
        $furtherAiManager = $this->manager(['club_id' => null, 'reputation' => 85]);
        $otherVacancy = $this->clubInLeague(['name' => 'Second Choice FC', 'rank' => 9]);
        $this->apply($this->vacantClub);
        $this->apply($otherVacancy);

        app(ManagerJobService::class)->fillVacancies($this->instance->fresh());

        $this->userManager->refresh();
        $this->assertSame($this->vacantClub->id, $this->userManager->club_id);
        $this->assertNotNull($this->userManager->contract);
        $this->assertSame('2028-09-01', $this->userManager->contract->contract_end->toDateString());
        $this->assertDatabaseHas('instances', [
            'id' => $this->instance->id, 'club_id' => $this->vacantClub->id, 'followed_competition_id' => $this->league->id,
        ]);
        $this->assertSame(
            [ManagerJobApplicationStatus::Accepted, ManagerJobApplicationStatus::Rejected],
            ManagerJobApplication::query()->orderBy('id')->pluck('status')->all()
        );
        $this->assertSame(['Vacant FC appoint you as manager'], DB::table('news')->pluck('title')->all());
        $this->assertSame($otherVacancy->id, $furtherAiManager->fresh()->club_id);
    }

    #[Test]
    public function the_board_prefers_a_closer_ai_manager_and_rejects_out_of_reach_applicants(): void
    {
        $closerAiManager = $this->manager(['club_id' => null, 'reputation' => 100]);
        $outOfReachClub = $this->clubInLeague(['name' => 'Giant FC', 'rank' => 20]);
        $this->apply($this->vacantClub);
        $this->apply($outOfReachClub);

        app(ManagerJobService::class)->fillVacancies($this->instance->fresh());

        $this->assertSame($this->vacantClub->id, $closerAiManager->fresh()->club_id);
        $this->assertNull($this->userManager->fresh()->club_id);
        $this->assertDatabaseHas('instances', ['id' => $this->instance->id, 'club_id' => null]);
        $this->assertSame(0, StaffCoaching::query()->where('club_id', $outOfReachClub->id)->count());
        $this->assertSame(0, ManagerJobApplication::query()->pending()->count());
        $this->assertEqualsCanonicalizing([
            'Vacant FC have appointed another manager.',
            'Giant FC are looking for a manager with a reputation around 200.',
        ], DB::table('news')->pluck('content')->all());
    }

    #[Test]
    public function vacant_clubs_use_their_assistant_manager_as_caretaker_for_tactics(): void
    {
        $this->seed(TacticsSeeder::class);
        $assistant = $this->manager(['club_id' => $this->vacantClub->id, 'type' => 'ASSISTANT_MANAGER']);

        $tactic = app(TacticsService::class)->currentForClub($this->vacantClub);

        $this->assertSame($assistant->tacticPreference->base_formation_id, $tactic->base_formation_id);
    }

    private function apply(Club $club): void
    {
        $this->from('/jobs')->post('/jobs/'.$club->id.'/apply')->assertSessionHasNoErrors();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function clubInLeague(array $attributes, bool $withManager = false): Club
    {
        $club = Club::factory()->create(array_merge(['instance_id' => $this->instance->id], $attributes));
        DB::table('competition_season')->insert([
            'instance_id' => $this->instance->id, 'season_id' => 1, 'competition_id' => $this->league->id, 'club_id' => $club->id,
        ]);

        if ($withManager) {
            $this->manager(['club_id' => $club->id]);
        }

        return $club;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function manager(array $attributes): StaffCoaching
    {
        return StaffCoaching::factory()->create(array_merge(['instance_id' => $this->instance->id, 'contract_id' => null], $attributes));
    }
}
