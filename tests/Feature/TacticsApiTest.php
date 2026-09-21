<?php

namespace Tests\Feature;

use App\Models\BaseData\BaseFormation;
use App\Models\Club;
use App\Models\Instance;
use App\Models\StaffCoaching;
use App\Models\StaffTacticPreference;
use App\Services\PersonService\PersonConfig\PersonTypes;
use App\Services\TacticsService\TacticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TacticsApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_formations_options_and_the_club_default_tactic(): void
    {
        [$instance] = $this->createManagedClub();
        $this->createFormation('5-3-2');
        $this->createFormation('4-4-2');

        $response = $this->apiRequest($instance)->getJson('/api/tactics');

        $response
            ->assertOk()
            ->assertJsonPath('data.tactic.mentality', 'balanced')
            ->assertJsonPath('data.tactic.pressing', 'medium')
            ->assertJsonPath('data.tactic.passing', 'mixed')
            ->assertJsonCount(2, 'data.formations')
            ->assertJsonPath('data.options.mentalities.0', 'defensive');
    }

    #[Test]
    public function it_creates_default_preferences_for_all_coaching_roles(): void
    {
        [$instance, $club] = $this->createManagedClub();
        $this->createFormation('4-4-2');
        $this->createFormation('4-3-3');

        foreach ([PersonTypes::ASSISTANT_MANAGER, PersonTypes::COACH, PersonTypes::YOUTH_COACH] as $role) {
            StaffCoaching::factory()->create([
                'instance_id' => $instance->id,
                'club_id' => $club->id,
                'type' => $role,
            ]);
        }

        foreach (StaffCoaching::query()->where('club_id', $club->id)->get() as $staff) {
            app(TacticsService::class)->ensureDefaultForStaff($staff);
        }

        $managerPreference = StaffTacticPreference::query()->where(
            'staff_coaching_id',
            StaffCoaching::query()->where('type', PersonTypes::MANAGER)->value('id'),
        )->value('base_formation_id');
        $assistantPreference = StaffTacticPreference::query()->where(
            'staff_coaching_id',
            StaffCoaching::query()->where('type', PersonTypes::ASSISTANT_MANAGER)->value('id'),
        )->value('base_formation_id');

        $this->assertSame((int) $managerPreference, (int) $assistantPreference);
        $this->assertDatabaseCount('staff_tactic_preferences', 4);
    }

    #[Test]
    public function it_updates_the_managed_club_tactic(): void
    {
        [$instance, $club] = $this->createManagedClub();
        $this->createFormation('5-3-2');
        $formation = $this->createFormation('4-3-3');

        $response = $this->apiRequest($instance)->putJson('/api/tactics', [
            'formation_id' => $formation->id,
            'mentality' => 'attacking',
            'pressing' => 'high',
            'passing' => 'short',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.formation.code', '4-3-3')
            ->assertJsonPath('data.mentality', 'attacking')
            ->assertJsonPath('data.pressing', 'high')
            ->assertJsonPath('data.passing', 'short');

        $this->assertDatabaseHas('staff_tactic_preferences', [
            'staff_coaching_id' => StaffCoaching::query()->where('club_id', $club->id)->value('id'),
            'base_formation_id' => $formation->id,
            'mentality' => 'attacking',
            'pressing' => 'high',
            'passing' => 'short',
        ]);

        $this->assertDatabaseHas('club_tactics', [
            'club_id' => $club->id,
            'base_formation_id' => $formation->id,
            'mentality' => 'attacking',
            'pressing' => 'high',
            'passing' => 'short',
        ]);
    }

    #[Test]
    public function it_rejects_an_inactive_formation(): void
    {
        [$instance] = $this->createManagedClub();
        $formation = $this->createFormation('4-4-2', false);

        $this->apiRequest($instance)->putJson('/api/tactics', [
            'formation_id' => $formation->id,
            'mentality' => 'balanced',
            'pressing' => 'medium',
            'passing' => 'mixed',
        ])->assertUnprocessable()->assertJsonValidationErrors('formation_id');
    }

    /**  array{0: Instance, 1: Club} */
    private function createManagedClub(): array
    {
        $instance = Instance::factory()->create(['instance_hash' => 'tactics-instance-'.uniqid()]);
        $club = Club::factory()->create(['instance_id' => $instance->id]);
        $instance->forceFill(['club_id' => $club->id])->saveQuietly();
        StaffCoaching::factory()->create([
            'instance_id' => $instance->id,
            'club_id' => $club->id,
            'type' => PersonTypes::MANAGER,
        ]);

        return [$instance->fresh(), $club->fresh()];
    }

    private function createFormation(string $code, bool $isActive = true): BaseFormation
    {
        $formation = BaseFormation::query()->create(['code' => $code, 'name' => $code, 'is_active' => $isActive]);
        $formation->slots()->create(['slot' => '1', 'position' => 'GK', 'x' => 50, 'y' => 90]);

        return $formation;
    }

    private function apiRequest(Instance $instance): self
    {
        return $this
            ->actingAs($instance->user)
            ->withHeaders(['instanceHash' => $instance->instance_hash]);
    }
}
