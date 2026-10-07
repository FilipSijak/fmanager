<?php

namespace Tests\Feature;

use App\Models\BaseData\BaseFormation;
use App\Models\Club;
use App\Models\Instance;
use App\Models\Player;
use App\Models\StaffCoaching;
use App\Models\StaffTacticPreference;
use App\Services\PersonService\PersonConfig\PersonTypes;
use App\Services\TacticsService\FormationTendency;
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
    public function it_uses_attacking_defaults_for_attacking_formations(): void
    {
        [$instance, $club] = $this->createManagedClub();
        $formation = $this->createFormation('4-3-3', true, FormationTendency::ATTACKING);
        $manager = StaffCoaching::query()->where('club_id', $club->id)->where('type', PersonTypes::MANAGER)->firstOrFail();

        app(TacticsService::class)->ensureDefaultForStaff($manager);

        $this->assertDatabaseHas('staff_tactic_preferences', [
            'staff_coaching_id' => $manager->id,
            'base_formation_id' => $formation->id,
            'mentality' => 'attacking',
            'pressing' => 'high',
            'passing' => 'short',
        ]);
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
            ...$this->instructionsPayload($formation),
            'mentality' => 'attacking',
            'pressing' => 'high',
            'passing' => 'long',
            'tackling' => 'hard',
            'offside_trap' => true,
            'men_behind_ball' => true,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.formation.code', '4-3-3')
            ->assertJsonPath('data.mentality', 'attacking')
            ->assertJsonPath('data.pressing', 'high')
            ->assertJsonPath('data.passing', 'long')
            ->assertJsonPath('data.tackling', 'hard')
            ->assertJsonPath('data.offside_trap', true)
            ->assertJsonPath('data.counter_attack', false)
            ->assertJsonPath('data.men_behind_ball', true);

        $this->assertDatabaseHas('staff_tactic_preferences', [
            'staff_coaching_id' => StaffCoaching::query()->where('club_id', $club->id)->value('id'),
            'base_formation_id' => $formation->id,
            'mentality' => 'attacking',
            'pressing' => 'high',
            'passing' => 'long',
            'tackling' => 'hard',
            'offside_trap' => true,
            'counter_attack' => false,
            'men_behind_ball' => true,
        ]);

        $this->assertDatabaseHas('club_tactics', [
            'club_id' => $club->id,
            'base_formation_id' => $formation->id,
            'mentality' => 'attacking',
            'pressing' => 'high',
            'passing' => 'long',
            'tackling' => 'hard',
            'offside_trap' => true,
            'counter_attack' => false,
            'men_behind_ball' => true,
        ]);
    }

    #[Test]
    public function it_stores_player_roles_on_the_club_tactic(): void
    {
        [$instance, $club] = $this->createManagedClub();
        $formation = $this->createFormation('4-4-2');
        $setPieceTaker = $this->createPlayer($instance, $club);
        $playmaker = $this->createPlayer($instance, $club);

        $response = $this->apiRequest($instance)->putJson('/api/tactics', [
            ...$this->instructionsPayload($formation),
            'free_kicks_left_player_id' => $setPieceTaker->id,
            'free_kicks_right_player_id' => $setPieceTaker->id,
            'corners_left_player_id' => $setPieceTaker->id,
            'corners_right_player_id' => null,
            'playmaker_player_id' => $playmaker->id,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.free_kicks_left_player_id', $setPieceTaker->id)
            ->assertJsonPath('data.corners_right_player_id', null)
            ->assertJsonPath('data.playmaker_player_id', $playmaker->id);

        $this->assertDatabaseHas('club_tactics', [
            'club_id' => $club->id,
            'free_kicks_left_player_id' => $setPieceTaker->id,
            'free_kicks_right_player_id' => $setPieceTaker->id,
            'corners_left_player_id' => $setPieceTaker->id,
            'corners_right_player_id' => null,
            'playmaker_player_id' => $playmaker->id,
        ]);
    }

    #[Test]
    public function it_rejects_a_player_role_for_a_player_from_another_club(): void
    {
        [$instance, $club] = $this->createManagedClub();
        $formation = $this->createFormation('4-4-2');
        $otherClub = Club::factory()->create(['instance_id' => $instance->id]);
        $outsider = $this->createPlayer($instance, $otherClub);

        $response = $this->apiRequest($instance)->putJson('/api/tactics', [
            ...$this->instructionsPayload($formation),
            'playmaker_player_id' => $outsider->id,
        ]);

        $response->assertUnprocessable()->assertJsonPath('error', 'One or more selected players do not belong to this club.');
        $this->assertDatabaseMissing('club_tactics', ['playmaker_player_id' => $outsider->id]);
    }

    #[Test]
    public function it_requires_every_team_instruction(): void
    {
        [$instance] = $this->createManagedClub();
        $formation = $this->createFormation('4-4-2');

        $this->apiRequest($instance)->putJson('/api/tactics', [
            'formation_id' => $formation->id,
            'mentality' => 'balanced',
            'pressing' => 'medium',
            'passing' => 'mixed',
        ])->assertUnprocessable()->assertJsonValidationErrors(['tackling', 'offside_trap', 'counter_attack', 'men_behind_ball']);
    }

    #[Test]
    public function it_rejects_an_inactive_formation(): void
    {
        [$instance] = $this->createManagedClub();
        $formation = $this->createFormation('4-4-2', false);

        $this->apiRequest($instance)->putJson('/api/tactics', $this->instructionsPayload($formation))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('formation_id');
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

    private function createFormation(string $code, bool $isActive = true, FormationTendency $tendency = FormationTendency::BALANCED): BaseFormation
    {
        $formation = BaseFormation::query()->create(['code' => $code, 'name' => $code, 'is_active' => $isActive, 'tactical_tendency' => $tendency]);
        $formation->slots()->create(['slot' => '1', 'position' => 'GK', 'x' => 50, 'y' => 90]);

        return $formation;
    }

    /** @return array<string, mixed> */
    private function instructionsPayload(BaseFormation $formation): array
    {
        return [
            'formation_id' => $formation->id,
            'mentality' => 'balanced',
            'pressing' => 'medium',
            'passing' => 'mixed',
            'tackling' => 'normal',
            'offside_trap' => false,
            'counter_attack' => false,
            'men_behind_ball' => false,
        ];
    }

    private function createPlayer(Instance $instance, Club $club): Player
    {
        return Player::factory()->create([
            'instance_id' => $instance->id,
            'club_id' => $club->id,
            'is_retired' => false,
        ]);
    }

    private function apiRequest(Instance $instance): self
    {
        return $this
            ->actingAs($instance->user)
            ->withHeaders(['instanceHash' => $instance->instance_hash]);
    }
}
