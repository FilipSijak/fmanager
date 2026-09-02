<?php

namespace Tests\Integration\Services\PersonService;

use App\Models\Instance;
use App\Models\Person;
use App\Models\Player;
use App\Services\PersonService\PersonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdatePlayerPotentialsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_updates_active_players_for_the_current_instance(): void
    {
        $instance = Instance::factory()->create(['id' => 1, 'instance_date' => '2027-06-16']);
        $otherInstance = Instance::factory()->create(['id' => 2]);

        $developingPlayer = $this->player($instance, '2006-06-16', 180, 120);
        $decliningPlayer = $this->player($instance, '1998-06-16', 180, 180);
        $retiredPlayer = $this->player($instance, '2006-06-16', 180, 120, true);
        $otherPlayer = $this->player($otherInstance, '2006-06-16', 180, 120);

        app(PersonService::class)->updatePlayerPotentials($instance);

        $this->assertSame(171, (int) $developingPlayer->fresh()->potential);
        $this->assertSame(176, (int) $decliningPlayer->fresh()->potential);
        $this->assertSame(120, (int) $retiredPlayer->fresh()->potential);
        $this->assertSame(120, (int) $otherPlayer->fresh()->potential);
    }

    private function player(
        Instance $instance,
        string $dateOfBirth,
        int $maxPotential,
        int $potential,
        bool $retired = false
    ): Player {
        $person = Person::factory()->create([
            'instance_id' => $instance->id,
            'dob' => $dateOfBirth,
        ]);

        return Player::factory()->create([
            'instance_id' => $instance->id,
            'person_id' => $person->id,
            'max_potential' => $maxPotential,
            'potential' => $potential,
            'is_retired' => $retired,
        ]);
    }
}
