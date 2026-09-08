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

    #[Test]
    public function it_reduces_attributes_above_the_new_age_adjusted_ceiling(): void
    {
        $instance = Instance::factory()->create(['id' => 1, 'instance_date' => '2027-06-16']);
        $player = $this->player($instance, '1998-06-16', 180, 180);
        $player->forceFill([
            'technical' => 100,
            'mental' => 100,
            'physical' => 100,
            'marking' => 12,
            'positioning' => 7,
            'strength' => 11,
        ])->save();

        app(PersonService::class)->updatePlayerPotentials($instance);

        $updatedPlayer = $player->fresh();
        $this->assertSame(176, (int) $updatedPlayer->potential);
        $this->assertSame(9, (int) $updatedPlayer->marking);
        $this->assertSame(7, (int) $updatedPlayer->positioning);
        $this->assertSame(9, (int) $updatedPlayer->strength);
    }

    #[Test]
    public function aging_never_increases_potential_category_potential_or_attributes(): void
    {
        $instance = Instance::factory()->create(['id' => 1, 'instance_date' => '2030-06-16']);
        $player = $this->player($instance, '2006-06-16', 180, 180);
        $player->forceFill([
            'technical' => 180,
            'mental' => 180,
            'physical' => 180,
            'marking' => 20,
            'positioning' => 20,
            'strength' => 20,
        ])->save();

        $personService = app(PersonService::class);
        $personService->updatePlayerPotentials($instance);
        $atPeak = $player->fresh();

        $instance->forceFill(['instance_date' => '2044-06-16'])->save();
        $personService->updatePlayerPotentials($instance->fresh());
        $atAge38 = $player->fresh();

        $this->assertLessThanOrEqual((float) $atPeak->potential, (float) $atAge38->potential);
        $this->assertLessThanOrEqual((int) $atPeak->current_technical_potential, (int) $atAge38->current_technical_potential);
        $this->assertLessThanOrEqual((int) $atPeak->current_mental_potential, (int) $atAge38->current_mental_potential);
        $this->assertLessThanOrEqual((int) $atPeak->current_physical_potential, (int) $atAge38->current_physical_potential);
        $this->assertLessThanOrEqual((int) $atPeak->marking, (int) $atAge38->marking);
        $this->assertLessThanOrEqual((int) $atPeak->positioning, (int) $atAge38->positioning);
        $this->assertLessThanOrEqual((int) $atPeak->strength, (int) $atAge38->strength);
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
