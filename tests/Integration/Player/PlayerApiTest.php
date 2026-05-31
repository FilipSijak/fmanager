<?php

namespace Tests\Integration\Player;

use App\Models\Club;
use App\Models\Instance;
use App\Models\Player;
use App\Models\PlayerContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlayerApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_a_player_profile_for_the_current_instance(): void
    {
        $instance = Instance::factory()->create([
            'id' => 1,
            'instance_hash' => 'current-instance',
        ]);
        $club = Club::factory()->create([
            'id' => 20,
            'instance_id' => $instance->id,
            'name' => 'Managed FC',
        ]);
        $contract = PlayerContract::factory()->create([
            'salary' => 1000,
            'contract_start' => '2024-07-01',
            'contract_end' => '2028-06-30',
        ]);
        $player = Player::factory()->create([
            'id' => 100,
            'instance_id' => $instance->id,
            'club_id' => $club->id,
            'contract_id' => $contract->id,
            'first_name' => 'Alpha',
            'last_name' => 'Player',
            'position' => 'CM',
            'country_code' => 'GB',
            'dob' => '2000-01-02',
            'potential' => 200,
            'max_potential' => 200,
            'corners' => 11,
            'decisions' => 12,
            'pace' => 13,
        ]);

        $response = $this
            ->withHeaders(['instanceHash' => 'current-instance'])
            ->getJson("/api/player/{$player->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $player->id)
            ->assertJsonPath('data.first_name', 'Alpha')
            ->assertJsonPath('data.last_name', 'Player')
            ->assertJsonPath('data.position', 'CM')
            ->assertJsonPath('data.country_code', 'GB')
            ->assertJsonPath('data.dob', '2000-01-02')
            ->assertJsonPath('data.club.id', $club->id)
            ->assertJsonPath('data.club.name', 'Managed FC')
            ->assertJsonPath('data.contract.salary', 1000)
            ->assertJsonPath('data.contract.contract_start', '2024-07-01')
            ->assertJsonPath('data.contract.contract_end', '2028-06-30')
            ->assertJsonPath('data.attributes.technical.corners', 11)
            ->assertJsonPath('data.attributes.mental.decisions', 12)
            ->assertJsonPath('data.attributes.physical.pace', 13)
            ->assertJsonMissingPath('data.potential')
            ->assertJsonMissingPath('data.max_potential');
    }

    #[Test]
    public function it_does_not_return_a_player_from_another_instance(): void
    {
        Instance::factory()->create([
            'id' => 1,
            'instance_hash' => 'current-instance',
        ]);
        $otherInstance = Instance::factory()->create([
            'id' => 2,
            'instance_hash' => 'other-instance',
        ]);
        $otherPlayer = Player::factory()->create([
            'id' => 100,
            'instance_id' => $otherInstance->id,
        ]);

        $response = $this
            ->withHeaders(['instanceHash' => 'current-instance'])
            ->getJson("/api/player/{$otherPlayer->id}");

        $response->assertNotFound();
    }
}
