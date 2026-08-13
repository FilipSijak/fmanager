<?php

namespace Tests\Integration\Services\SeasonService;

use App\Models\Club;
use App\Models\Instance;
use App\Models\StaffCoaching;
use App\Models\StaffPhysio;
use App\Models\StaffScout;
use App\Services\SeasonService\StaffCountValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StaffCountValidatorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_calculates_missing_active_staff_from_club_requirements_and_free_agent_percentage(): void
    {
        $instance = Instance::factory()->create(['id' => 1]);
        Club::factory()->count(2)->create(['instance_id' => $instance->id]);

        StaffCoaching::factory()->count(20)->create(['instance_id' => $instance->id]);
        StaffScout::factory()->count(10)->create(['instance_id' => $instance->id]);
        StaffPhysio::factory()->count(9)->create(['instance_id' => $instance->id]);
        StaffPhysio::factory()->create([
            'instance_id' => $instance->id,
            'is_retired' => true,
        ]);

        $this->assertSame(11, app(StaffCountValidator::class)->missingStaffCount($instance));
    }

    #[Test]
    public function it_never_returns_a_negative_missing_count(): void
    {
        $instance = Instance::factory()->create(['id' => 1]);
        Club::factory()->create(['instance_id' => $instance->id]);
        StaffCoaching::factory()->count(26)->create(['instance_id' => $instance->id]);

        $this->assertSame(0, app(StaffCountValidator::class)->missingStaffCount($instance));
    }
}
