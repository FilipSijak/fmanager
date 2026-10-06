<?php

namespace Tests\Feature;

use App\Models\BaseData\BaseFormation;
use App\Models\Club;
use App\Models\Instance;
use App\Models\Player;
use App\Models\StaffCoaching;
use App\Services\PersonService\PersonConfig\PersonTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SquadLineupApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_an_empty_lineup_when_nothing_has_been_saved(): void
    {
        [$instance] = $this->createManagedClub();
        $this->createFormation('4-4-2');

        $response = $this->apiRequest($instance)->getJson('/api/squad/lineup');

        $response->assertOk()->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_saves_a_lineup_with_starters_and_substitutes(): void
    {
        [$instance, $club] = $this->createManagedClub();
        $this->createFormation('4-4-2');
        $goalkeeper = $this->createPlayer($instance, $club, 'GK');
        $substitute = $this->createPlayer($instance, $club, 'AMC');

        $response = $this->apiRequest($instance)->postJson('/api/squad/lineup', [
            'assignments' => [
                ['slot' => '1', 'player_id' => $goalkeeper->id, 'position' => 'GK'],
                ['slot' => 'SUB1', 'player_id' => $substitute->id, 'position' => 'AMC'],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['slot' => '1', 'position' => 'GK', 'player_id' => $goalkeeper->id])
            ->assertJsonFragment(['slot' => 'SUB1', 'position' => 'AMC', 'player_id' => $substitute->id]);

        $this->assertDatabaseHas('club_tactic_players_slots', [
            'club_id' => $club->id,
            'instance_id' => $instance->id,
            'slot' => '1',
            'player_id' => $goalkeeper->id,
        ]);
    }

    #[Test]
    public function it_replaces_the_previous_lineup_on_save(): void
    {
        [$instance, $club] = $this->createManagedClub();
        $this->createFormation('4-4-2');
        $first = $this->createPlayer($instance, $club, 'GK');
        $second = $this->createPlayer($instance, $club, 'CB');

        $this->apiRequest($instance)->postJson('/api/squad/lineup', [
            'assignments' => [['slot' => '1', 'player_id' => $first->id, 'position' => 'GK']],
        ])->assertOk();

        $response = $this->apiRequest($instance)->postJson('/api/squad/lineup', [
            'assignments' => [['slot' => '2', 'player_id' => $second->id, 'position' => 'CB']],
        ]);

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertDatabaseCount('club_tactic_players_slots', 1);
        $this->assertDatabaseHas('club_tactic_players_slots', [
            'club_id' => $club->id,
            'slot' => '2',
            'player_id' => $second->id,
        ]);
    }

    #[Test]
    public function it_rejects_a_player_who_does_not_belong_to_the_club(): void
    {
        [$instance, $club] = $this->createManagedClub();
        $this->createFormation('4-4-2');
        $otherInstance = Instance::factory()->create(['instance_hash' => 'other-instance-'.uniqid()]);
        $otherClub = Club::factory()->create(['instance_id' => $otherInstance->id]);
        $outsider = $this->createPlayer($otherInstance, $otherClub, 'GK');

        $response = $this->apiRequest($instance)->postJson('/api/squad/lineup', [
            'assignments' => [['slot' => '1', 'player_id' => $outsider->id, 'position' => 'GK']],
        ]);

        $response->assertUnprocessable()->assertJsonPath('error', 'One or more selected players do not belong to this club.');
        $this->assertDatabaseCount('club_tactic_players_slots', 0);
    }

    #[Test]
    public function it_rejects_duplicate_slots_or_players_in_the_same_request(): void
    {
        [$instance, $club] = $this->createManagedClub();
        $this->createFormation('4-4-2');
        $player = $this->createPlayer($instance, $club, 'GK');

        $response = $this->apiRequest($instance)->postJson('/api/squad/lineup', [
            'assignments' => [
                ['slot' => '1', 'player_id' => $player->id, 'position' => 'GK'],
                ['slot' => '1', 'player_id' => $player->id, 'position' => 'GK'],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['assignments.0.slot', 'assignments.0.player_id']);
    }

    /** @return array{0: Instance, 1: Club} */
    private function createManagedClub(): array
    {
        $instance = Instance::factory()->create(['instance_hash' => 'squad-lineup-instance-'.uniqid()]);
        $club = Club::factory()->create(['instance_id' => $instance->id]);
        $instance->forceFill(['club_id' => $club->id])->saveQuietly();
        StaffCoaching::factory()->create([
            'instance_id' => $instance->id,
            'club_id' => $club->id,
            'type' => PersonTypes::MANAGER,
        ]);

        return [$instance->fresh(), $club->fresh()];
    }

    private function createFormation(string $code): BaseFormation
    {
        $formation = BaseFormation::query()->create(['code' => $code, 'name' => $code, 'is_active' => true]);
        $formation->slots()->create(['slot' => '1', 'position' => 'GK', 'x' => 50, 'y' => 90]);
        $formation->slots()->create(['slot' => '2', 'position' => 'CB', 'x' => 30, 'y' => 70]);

        return $formation;
    }

    private function createPlayer(Instance $instance, Club $club, string $position): Player
    {
        return Player::factory()->create([
            'instance_id' => $instance->id,
            'club_id' => $club->id,
            'position' => $position,
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
