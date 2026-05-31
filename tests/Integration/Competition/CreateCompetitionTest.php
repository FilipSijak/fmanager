<?php

namespace Tests\Integration\Competition;

use App\Models\Competition;
use App\Models\Instance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreateCompetitionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_a_competition_for_the_current_instance(): void
    {
        $instance = Instance::factory()->create([
            'id' => 1,
            'instance_hash' => 'current-instance',
        ]);
        $competition = Competition::factory()->create([
            'id' => 10,
            'instance_id' => $instance->id,
            'name' => 'Premier League',
            'country_code' => 'GB',
            'type' => 'league',
            'groups' => 0,
        ]);

        $response = $this
            ->withHeaders(['instanceHash' => 'current-instance'])
            ->getJson("/api/competition/{$competition->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $competition->id)
            ->assertJsonPath('data.name', 'Premier League')
            ->assertJsonPath('data.country_code', 'GB')
            ->assertJsonPath('data.type', 'league')
            ->assertJsonPath('data.groups', 0);
    }

    #[Test]
    public function it_does_not_return_a_competition_from_another_instance(): void
    {
        Instance::factory()->create([
            'id' => 1,
            'instance_hash' => 'current-instance',
        ]);
        $otherInstance = Instance::factory()->create([
            'id' => 2,
            'instance_hash' => 'other-instance',
        ]);
        $otherCompetition = Competition::factory()->create([
            'id' => 10,
            'instance_id' => $otherInstance->id,
        ]);

        $response = $this
            ->withHeaders(['instanceHash' => 'current-instance'])
            ->getJson("/api/competition/{$otherCompetition->id}");

        $response->assertNotFound();
    }
}
