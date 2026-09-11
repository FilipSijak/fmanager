<?php

namespace Tests\Feature\Integration\Stadium;

use App\Models\Instance;
use App\Models\Stadium;
use App\Models\StadiumStand;
use App\StadiumStandPosition;
use App\StadiumStandStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StadiumCapacityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_sums_total_and_active_stand_capacity(): void
    {
        $stadium = Stadium::factory()->create(['instance_id' => Instance::factory()->create()->id, 'capacity' => 0, 'active_capacity' => 0]);

        StadiumStand::factory()->create(['stadium_id' => $stadium->id, 'position' => StadiumStandPosition::NORTH, 'capacity' => 10000, 'status' => StadiumStandStatus::ACTIVE]);
        StadiumStand::factory()->create(['stadium_id' => $stadium->id, 'position' => StadiumStandPosition::NORTH_EAST, 'capacity' => 5000, 'status' => StadiumStandStatus::UNDER_CONSTRUCTION]);
        StadiumStand::factory()->create(['stadium_id' => $stadium->id, 'position' => StadiumStandPosition::SOUTH, 'capacity' => 2000, 'status' => null]);

        $stadium->refresh();

        $this->assertSame(17000, $stadium->capacity);
        $this->assertSame(10000, $stadium->active_capacity);
    }

    #[Test]
    public function it_recalculates_capacity_when_a_stand_changes_or_is_deleted(): void
    {
        $stadium = Stadium::factory()->create(['instance_id' => Instance::factory()->create()->id, 'capacity' => 0, 'active_capacity' => 0]);
        $stand = StadiumStand::factory()->create(['stadium_id' => $stadium->id, 'position' => StadiumStandPosition::WEST, 'capacity' => 12000, 'status' => StadiumStandStatus::UNDER_CONSTRUCTION]);

        $stand->update(['status' => StadiumStandStatus::ACTIVE, 'capacity' => 15000]);
        $stadium->refresh();

        $this->assertSame(15000, $stadium->capacity);
        $this->assertSame(15000, $stadium->active_capacity);

        $stand->delete();
        $stadium->refresh();

        $this->assertSame(0, $stadium->capacity);
        $this->assertSame(0, $stadium->active_capacity);
    }
}
