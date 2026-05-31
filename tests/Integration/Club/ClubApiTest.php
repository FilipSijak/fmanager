<?php

namespace Tests\Integration\Club;

use App\Models\Account;
use App\Models\Club;
use App\Models\Instance;
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
