<?php

namespace Tests\Integration\Instance;

use App\Models\BaseData\BaseStadiumStandCapacityLimit;
use App\Models\Instance;
use App\Models\Stadium;
use App\Services\InstanceService\InstanceData\InitialSeed;
use App\StadiumStandStatus;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InitialSeedTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_seeds_normalized_stadium_capacities_and_active_stands(): void
    {
        (new DatabaseSeeder)->run();
        $instance = Instance::factory()->create();

        app(InitialSeed::class)->seedStadiumsFromBaseTable($instance->id);

        $stadiums = Stadium::query()->where('instance_id', $instance->id)->with('stands')->get();

        $this->assertNotEmpty($stadiums);

        foreach ($stadiums as $stadium) {
            $limits = BaseStadiumStandCapacityLimit::query()
                ->where('stadium_type', $stadium->type->value)
                ->pluck('maximum_capacity', 'position');

            $this->assertSame($limits->count(), $stadium->stands->count());
            $this->assertSame($stadium->capacity, $stadium->active_capacity);
            $this->assertSame(
                $stadium->capacity,
                $stadium->stands->sum(fn ($stand): int => (int) $stand->capacity),
            );

            foreach ($stadium->stands as $stand) {
                $this->assertLessThanOrEqual(
                    (int) $limits->get($stand->position->value),
                    (int) $stand->capacity,
                );
                $this->assertSame(0, $stand->capacity % 1000);

                if ($stand->capacity > 0) {
                    $this->assertSame(StadiumStandStatus::ACTIVE, $stand->status);
                }
            }
        }
    }
}
