<?php

namespace Tests\Feature;

use App\Models\BaseData\BaseFormation;
use App\Models\Club;
use App\Models\Instance;
use App\Models\Player;
use App\Models\StaffCoaching;
use App\Services\PersonService\PersonConfig\PersonTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $starters = $this->startingAssignments($instance, $club);
        $substitute = $this->createPlayer($instance, $club, 'AMC');

        $response = $this->apiRequest($instance)->postJson('/api/squad/lineup', [
            'assignments' => [
                ...$starters,
                ['slot' => 'SUB1', 'player_id' => $substitute->id, 'position' => 'SUB'],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonCount(12, 'data')
            ->assertJsonFragment(['slot' => '1', 'position' => 'GK', 'player_id' => $starters[0]['player_id']])
            ->assertJsonFragment(['slot' => 'SUB1', 'position' => 'SUB', 'player_id' => $substitute->id]);

        $this->assertDatabaseHas('club_tactic_players_slots', [
            'club_id' => $club->id,
            'instance_id' => $instance->id,
            'slot' => '1',
            'player_id' => $starters[0]['player_id'],
        ]);
    }

    #[Test]
    public function it_saves_a_lineup_without_substitutes(): void
    {
        [$instance, $club] = $this->createManagedClub();
        $this->createFormation('4-4-2');

        $response = $this->apiRequest($instance)->postJson('/api/squad/lineup', [
            'assignments' => $this->startingAssignments($instance, $club),
        ]);

        $response->assertOk()->assertJsonCount(11, 'data');
        $this->assertDatabaseCount('club_tactic_players_slots', 11);
    }

    #[Test]
    public function it_replaces_the_previous_lineup_on_save(): void
    {
        [$instance, $club] = $this->createManagedClub();
        $this->createFormation('4-4-2');
        $first = $this->startingAssignments($instance, $club);
        $second = $this->startingAssignments($instance, $club);

        $this->apiRequest($instance)->postJson('/api/squad/lineup', ['assignments' => $first])->assertOk();

        $response = $this->apiRequest($instance)->postJson('/api/squad/lineup', ['assignments' => $second]);

        $response->assertOk()->assertJsonCount(11, 'data');
        $this->assertDatabaseCount('club_tactic_players_slots', 11);
        $this->assertDatabaseHas('club_tactic_players_slots', ['club_id' => $club->id, 'slot' => '1', 'player_id' => $second[0]['player_id']]);
        $this->assertDatabaseMissing('club_tactic_players_slots', ['player_id' => $first[0]['player_id']]);
    }

    #[Test]
    public function it_clears_the_lineup_when_no_players_are_selected(): void
    {
        [$instance, $club] = $this->createManagedClub();
        $this->createFormation('4-4-2');
        $this->apiRequest($instance)->postJson('/api/squad/lineup', [
            'assignments' => $this->startingAssignments($instance, $club),
        ])->assertOk();

        $response = $this->apiRequest($instance)->postJson('/api/squad/lineup', ['assignments' => []]);

        $response->assertOk()->assertJsonCount(0, 'data');
        $this->assertDatabaseCount('club_tactic_players_slots', 0);
    }

    /** @return array<string, array{int, int}> */
    public static function incompleteStartingElevens(): array
    {
        return [
            'ten starters' => [10, 0],
            'twelve starters' => [12, 0],
            'ten starters and a substitute' => [10, 1],
        ];
    }

    #[Test]
    #[DataProvider('incompleteStartingElevens')]
    public function it_rejects_a_lineup_without_exactly_11_starters_and_keeps_the_saved_one(int $starterCount, int $substituteCount): void
    {
        [$instance, $club] = $this->createManagedClub();
        $this->createFormation('4-4-2');
        $saved = $this->startingAssignments($instance, $club);
        $this->apiRequest($instance)->postJson('/api/squad/lineup', ['assignments' => $saved])->assertOk();
        $substitutes = collect($substituteCount > 0 ? range(1, $substituteCount) : [])->map(fn (int $number): array => [
            'slot' => 'SUB'.$number,
            'player_id' => $this->createPlayer($instance, $club, 'CM')->id,
            'position' => 'SUB',
        ])->all();

        $response = $this->apiRequest($instance)->postJson('/api/squad/lineup', [
            'assignments' => [...$this->startingAssignments($instance, $club, $starterCount), ...$substitutes],
        ]);

        $response->assertUnprocessable()->assertJsonPath('error', 'A lineup needs exactly 11 starting players.');
        $this->assertDatabaseCount('club_tactic_players_slots', 11);
        $this->assertDatabaseHas('club_tactic_players_slots', ['slot' => '1', 'player_id' => $saved[0]['player_id']]);
    }

    #[Test]
    public function it_rejects_a_player_who_does_not_belong_to_the_club(): void
    {
        [$instance, $club] = $this->createManagedClub();
        $this->createFormation('4-4-2');
        $otherInstance = Instance::factory()->create(['instance_hash' => 'other-instance-'.uniqid()]);
        $otherClub = Club::factory()->create(['instance_id' => $otherInstance->id]);
        $outsider = $this->createPlayer($otherInstance, $otherClub, 'GK');
        $assignments = $this->startingAssignments($instance, $club);
        $assignments[0]['player_id'] = $outsider->id;

        $response = $this->apiRequest($instance)->postJson('/api/squad/lineup', [
            'assignments' => $assignments,
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

    /**
     * Assignments for new club players in starting slots "1", "2", ... (11 by default).
     *
     * @return list<array{slot: string, player_id: int, position: string}>
     */
    private function startingAssignments(Instance $instance, Club $club, int $count = 11): array
    {
        $positions = ['GK', 'LB', 'CB', 'CB', 'RB', 'LM', 'CM', 'CM', 'RM', 'ST', 'ST', 'ST'];

        return array_map(fn (int $index): array => [
            'slot' => (string) ($index + 1),
            'player_id' => $this->createPlayer($instance, $club, $positions[$index])->id,
            'position' => $positions[$index],
        ], range(0, $count - 1));
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
