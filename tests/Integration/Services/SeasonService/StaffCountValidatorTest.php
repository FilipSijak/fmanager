<?php

namespace Tests\Integration\Services\SeasonService;

use App\Models\Club;
use App\Models\Instance;
use App\Models\StaffCoaching;
use App\Models\StaffPhysio;
use App\Models\StaffScout;
use App\Services\PersonService\PersonConfig\PersonTypes;
use App\Services\SeasonService\StaffCountValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StaffCountValidatorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_calculates_missing_active_staff_for_each_role(): void
    {
        $instance = Instance::factory()->create(['id' => 1]);
        Club::factory()->count(2)->create(['instance_id' => $instance->id]);

        StaffCoaching::factory()->count(2)->create([
            'instance_id' => $instance->id,
            'type' => PersonTypes::MANAGER,
        ]);
        StaffCoaching::factory()->create([
            'instance_id' => $instance->id,
            'type' => PersonTypes::ASSISTANT_MANAGER,
        ]);
        StaffCoaching::factory()->count(14)->create([
            'instance_id' => $instance->id,
            'type' => PersonTypes::COACH,
        ]);
        StaffCoaching::factory()->count(8)->create([
            'instance_id' => $instance->id,
            'type' => PersonTypes::YOUTH_COACH,
        ]);
        StaffScout::factory()->count(10)->create(['instance_id' => $instance->id]);
        StaffPhysio::factory()->count(8)->create([
            'instance_id' => $instance->id,
            'team_type' => 'FIRST_TEAM',
        ]);
        StaffPhysio::factory()->create([
            'instance_id' => $instance->id,
            'team_type' => 'YOUTH_TEAM',
        ]);
        StaffScout::factory()->create([
            'instance_id' => $instance->id,
            'is_retired' => true,
        ]);

        $this->assertSame([
            PersonTypes::MANAGER => 0,
            PersonTypes::ASSISTANT_MANAGER => 1,
            PersonTypes::COACH => 2,
            PersonTypes::YOUTH_COACH => 0,
            PersonTypes::SCOUT => 2,
            PersonTypes::PHYSIO => 0,
            PersonTypes::YOUTH_PHYSIO => 1,
        ], app(StaffCountValidator::class)->missingStaffByRole($instance));
    }

    #[Test]
    public function surplus_in_one_role_does_not_hide_a_shortage_in_another(): void
    {
        $instance = Instance::factory()->create(['id' => 1]);
        Club::factory()->create(['instance_id' => $instance->id]);
        StaffCoaching::factory()->count(25)->create([
            'instance_id' => $instance->id,
            'type' => PersonTypes::MANAGER,
        ]);

        $missing = app(StaffCountValidator::class)->missingStaffByRole($instance);

        $this->assertSame(0, $missing[PersonTypes::MANAGER]);
        $this->assertSame(1, $missing[PersonTypes::ASSISTANT_MANAGER]);
        $this->assertSame(8, $missing[PersonTypes::COACH]);
        $this->assertSame(6, $missing[PersonTypes::SCOUT]);
    }

    #[Test]
    public function unemployed_staff_still_count_toward_the_baseline_population(): void
    {
        $instance = Instance::factory()->create(['id' => 1]);
        Club::factory()->create(['instance_id' => $instance->id]);
        StaffCoaching::factory()->count(8)->create([
            'instance_id' => $instance->id,
            'type' => PersonTypes::COACH,
            'club_id' => null,
            'contract_id' => null,
        ]);

        $missing = app(StaffCountValidator::class)->missingStaffByRole($instance);

        $this->assertSame(0, $missing[PersonTypes::COACH]);
    }
}
