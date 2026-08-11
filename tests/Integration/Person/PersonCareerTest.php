<?php

namespace Tests\Integration\Person;

use App\Models\Instance;
use App\Models\Person;
use App\Models\Player;
use App\Models\StaffCoaching;
use App\Models\StaffPhysio;
use App\Models\StaffScout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PersonCareerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function one_person_can_have_independent_staff_careers(): void
    {
        $instance = Instance::factory()->create(['id' => 1]);
        $person = Person::factory()->create([
            'instance_id' => $instance->id,
            'first_name' => 'Career',
            'last_name' => 'Changer',
            'dob' => '1980-01-01',
            'country_code' => 'GB',
        ]);

        $player = Player::factory()->create([
            'instance_id' => $instance->id,
            'person_id' => $person->id,
            'is_retired' => true,
        ]);
        $staff = StaffCoaching::factory()->create([
            'instance_id' => $instance->id,
            'person_id' => $person->id,
            'type' => 'MANAGER',
            'is_retired' => false,
        ]);
        $physio = StaffPhysio::factory()->create([
            'instance_id' => $instance->id,
            'person_id' => $person->id,
        ]);
        $scout = StaffScout::factory()->create([
            'instance_id' => $instance->id,
            'person_id' => $person->id,
        ]);

        $this->assertSame($person->id, $player->person->id);
        $this->assertSame('Career', $player->first_name);
        $this->assertSame($player->id, $person->player->id);
        $this->assertSame($staff->id, $person->coachingCareers->first()->id);
        $this->assertSame($physio->id, $person->physioCareers->first()->id);
        $this->assertSame($scout->id, $person->scoutCareers->first()->id);
        $this->assertTrue(Player::retired()->whereKey($player->id)->exists());
        $this->assertTrue(StaffCoaching::active()->whereKey($staff->id)->exists());
        $this->assertTrue(StaffPhysio::active()->whereKey($physio->id)->exists());
        $this->assertTrue(StaffScout::active()->whereKey($scout->id)->exists());
    }
}
