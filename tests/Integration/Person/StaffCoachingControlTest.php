<?php

namespace Tests\Integration\Person;

use App\Models\Manager;
use App\Models\StaffCoaching;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StaffCoachingControlTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function ai_controlled_scope_excludes_managers_owned_by_users(): void
    {
        $userManager = Manager::factory()->create();
        $userStaff = $userManager->staffCoaching;
        $aiStaff = StaffCoaching::factory()->create(['instance_id' => $userManager->instance_id]);

        $this->assertTrue($userStaff->userManager->is($userManager));
        $this->assertNull($aiStaff->userManager);
        $this->assertSame(
            [$aiStaff->id],
            StaffCoaching::query()->forInstance($userManager->instance_id)->aiControlled()->pluck('id')->all()
        );
    }
}
