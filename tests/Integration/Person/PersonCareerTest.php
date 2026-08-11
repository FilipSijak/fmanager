<?php

namespace Tests\Integration\Person;

use App\Models\Instance;
use App\Models\Person;
use App\Models\Player;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PersonCareerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function one_person_can_have_a_retired_player_career_and_an_active_staff_career(): void
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
        $staff = Staff::factory()->create([
            'instance_id' => $instance->id,
            'person_id' => $person->id,
            'type' => 'MANAGER',
            'is_retired' => false,
        ]);

        $this->assertSame($person->id, $player->person->id);
        $this->assertSame('Career', $player->first_name);
        $this->assertSame($player->id, $person->player->id);
        $this->assertSame($staff->id, $person->staffCareers->first()->id);
        $this->assertTrue(Player::retired()->whereKey($player->id)->exists());
        $this->assertTrue(Staff::active()->whereKey($staff->id)->exists());
    }
}
