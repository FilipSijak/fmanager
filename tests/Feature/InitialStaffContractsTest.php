<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Instance;
use App\Repositories\StaffRepository;
use App\Services\PersonService\PersonService;
use App\Support\GameContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class InitialStaffContractsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function failed_staff_persistence_rolls_back_contract_creation(): void
    {
        $instance = Instance::factory()->create(['instance_date' => '2026-07-01']);
        $club = Club::factory()->create(['instance_id' => $instance->id, 'rank' => 10]);
        app(GameContext::class)->set($instance->id, 1, '2026-07-01');
        $this->partialMock(StaffRepository::class)->shouldReceive('bulkStaffInsert')
            ->once()->andThrow(new RuntimeException('Staff persistence failed.'));

        try {
            app(PersonService::class)->initialStaffClubSeed($club);
            $this->fail('Expected staff persistence to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Staff persistence failed.', $exception->getMessage());
        }

        $this->assertDatabaseCount('staff_contracts', 0);
        $this->assertDatabaseCount('people', 0);
        $this->assertDatabaseCount('staff_coaching', 0);
        $this->assertDatabaseCount('staff_scouts', 0);
        $this->assertDatabaseCount('staff_physio', 0);
    }
}
