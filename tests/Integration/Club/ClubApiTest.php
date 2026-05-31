<?php

namespace Tests\Integration\Club;

use App\Models\Account;
use App\Models\Club;
use App\Models\Instance;
use App\Models\Player;
use App\Models\PlayerContract;
use App\Models\Stadium;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClubApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_a_club_profile_for_the_current_instance(): void
    {
        $instance = Instance::factory()->create([
            'id' => 1,
            'instance_hash' => 'current-instance',
        ]);
        $stadium = Stadium::factory()->create([
            'id' => 10,
            'instance_id' => $instance->id,
            'name' => 'Main Stadium',
            'capacity' => 50000,
        ]);
        $club = Club::factory()->create([
            'id' => 20,
            'instance_id' => $instance->id,
            'name' => 'Managed FC',
            'country_code' => 'GB',
            'stadium_id' => $stadium->id,
            'rank' => 12,
            'rank_academy' => 34,
            'rank_training' => 56,
        ]);
        Account::factory()->create([
            'club_id' => $club->id,
            'balance' => 1000,
            'transfer_budget' => 2000,
        ]);

        $response = $this
            ->withHeaders(['instanceHash' => 'current-instance'])
            ->getJson("/api/club/{$club->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $club->id)
            ->assertJsonPath('data.name', 'Managed FC')
            ->assertJsonPath('data.country_code', 'GB')
            ->assertJsonPath('data.rank', 12)
            ->assertJsonPath('data.rank_academy', 34)
            ->assertJsonPath('data.rank_training', 56)
            ->assertJsonPath('data.stadium.id', $stadium->id)
            ->assertJsonPath('data.stadium.name', 'Main Stadium')
            ->assertJsonPath('data.stadium.capacity', 50000)
            ->assertJsonPath('data.account.balance', 1000)
            ->assertJsonPath('data.account.transfer_budget', 2000);
    }

    #[Test]
    public function it_returns_the_current_instance_club_squad(): void
    {
        $instance = Instance::factory()->create([
            'id' => 1,
            'instance_hash' => 'current-instance',
        ]);
        $club = Club::factory()->create([
            'id' => 20,
            'instance_id' => $instance->id,
        ]);
        $otherClub = Club::factory()->create([
            'id' => 30,
            'instance_id' => $instance->id,
        ]);
        Instance::factory()->create([
            'id' => 2,
            'instance_hash' => 'other-instance',
        ]);

        $firstContract = PlayerContract::factory()->create(['salary' => 200]);
        $secondContract = PlayerContract::factory()->create(['salary' => 400]);
        $firstPlayer = Player::factory()->create([
            'id' => 100,
            'instance_id' => $instance->id,
            'club_id' => $club->id,
            'contract_id' => $firstContract->id,
            'first_name' => 'Alpha',
            'last_name' => 'Midfielder',
            'position' => 'CM',
            'country_code' => 'GB',
            'value' => 1000,
        ]);
        $secondPlayer = Player::factory()->create([
            'id' => 101,
            'instance_id' => $instance->id,
            'club_id' => $club->id,
            'contract_id' => $secondContract->id,
            'first_name' => 'Beta',
            'last_name' => 'Striker',
            'position' => 'ST',
            'country_code' => 'FR',
            'value' => 3000,
        ]);
        Player::factory()->create([
            'instance_id' => $instance->id,
            'club_id' => $otherClub->id,
            'first_name' => 'OtherClub',
            'last_name' => 'Player',
        ]);
        Player::factory()->create([
            'instance_id' => 2,
            'club_id' => $club->id,
            'first_name' => 'OtherInstance',
            'last_name' => 'Player',
        ]);

        $response = $this
            ->withHeaders(['instanceHash' => 'current-instance'])
            ->getJson("/api/club/{$club->id}/squad");

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $firstPlayer->id)
            ->assertJsonPath('data.0.first_name', 'Alpha')
            ->assertJsonPath('data.0.last_name', 'Midfielder')
            ->assertJsonPath('data.0.position', 'CM')
            ->assertJsonPath('data.0.country_code', 'GB')
            ->assertJsonPath('data.0.value', 1000)
            ->assertJsonPath('data.0.salary', 200)
            ->assertJsonPath('data.1.id', $secondPlayer->id);

        $playerFirstNames = collect($response->json('data'))->pluck('first_name')->all();

        $this->assertNotContains('OtherClub', $playerFirstNames);
        $this->assertNotContains('OtherInstance', $playerFirstNames);
    }

    #[Test]
    public function it_does_not_return_a_squad_for_a_club_from_another_instance(): void
    {
        Instance::factory()->create([
            'id' => 1,
            'instance_hash' => 'current-instance',
        ]);
        $otherInstance = Instance::factory()->create([
            'id' => 2,
            'instance_hash' => 'other-instance',
        ]);
        $otherClub = Club::factory()->create([
            'id' => 20,
            'instance_id' => $otherInstance->id,
        ]);

        $response = $this
            ->withHeaders(['instanceHash' => 'current-instance'])
            ->getJson("/api/club/{$otherClub->id}/squad");

        $response->assertNotFound();
    }

    #[Test]
    public function it_does_not_return_a_club_from_another_instance(): void
    {
        Instance::factory()->create([
            'id' => 1,
            'instance_hash' => 'current-instance',
        ]);
        $otherInstance = Instance::factory()->create([
            'id' => 2,
            'instance_hash' => 'other-instance',
        ]);
        $otherClub = Club::factory()->create([
            'id' => 20,
            'instance_id' => $otherInstance->id,
        ]);

        $response = $this
            ->withHeaders(['instanceHash' => 'current-instance'])
            ->getJson("/api/club/{$otherClub->id}");

        $response->assertNotFound();
    }
}
