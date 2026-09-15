<?php

namespace Tests\Integration\Stadium;

use App\Models\BaseData\BaseCommercialCategory;
use App\Models\Instance;
use App\Models\Stadium;
use App\Models\StadiumCommercialVenue;
use App\Models\StadiumStand;
use App\Models\StadiumStandConstruction;
use App\Services\CommercialService\CommercialVenueSize;
use App\StadiumStandPosition;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StadiumIntegrityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_rejects_a_commercial_venue_from_another_instance(): void
    {
        $stadiumInstance = Instance::factory()->create();
        $venueInstance = Instance::factory()->create();
        $stadium = Stadium::factory()->create(['instance_id' => $stadiumInstance->id]);
        $category = BaseCommercialCategory::query()->forceCreate([
            'slug' => 'restaurant',
            'name' => 'Restaurant',
        ]);

        $this->expectException(QueryException::class);

        StadiumCommercialVenue::query()->create([
            'instance_id' => $venueInstance->id,
            'stadium_id' => $stadium->id,
            'category_id' => $category->id,
            'size' => CommercialVenueSize::SMALL,
        ]);
    }

    #[Test]
    public function it_rejects_construction_from_another_instance(): void
    {
        $stadiumInstance = Instance::factory()->create();
        $constructionInstance = Instance::factory()->create();
        $stadium = Stadium::factory()->create(['instance_id' => $stadiumInstance->id]);
        $stand = StadiumStand::factory()->create([
            'stadium_id' => $stadium->id,
            'position' => StadiumStandPosition::NORTH,
        ]);

        $this->expectException(QueryException::class);

        StadiumStandConstruction::query()->create([
            'instance_id' => $constructionInstance->id,
            'stadium_id' => $stadium->id,
            'stadium_stand_id' => $stand->id,
            'target_capacity' => 1000,
            'capacity_increase' => 1000,
            'started_at' => '2026-09-15',
            'completes_at' => '2026-09-22',
        ]);
    }
}
