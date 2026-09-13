<?php

namespace Tests\Integration\Commercial;

use App\Models\BaseData\BaseCommercialCategory;
use App\Models\Instance;
use App\Models\Stadium;
use App\Models\StadiumCommercialVenue;
use App\Services\CommercialService\CommercialVenueSize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StadiumCommercialVenueTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_stores_a_size_for_a_stadium_commercial_venue(): void
    {
        $instance = Instance::factory()->create();
        $stadium = Stadium::factory()->create(['instance_id' => $instance->id]);
        $category = BaseCommercialCategory::query()->forceCreate(['slug' => 'events_venue', 'name' => 'Events Venue']);

        $venue = StadiumCommercialVenue::factory()->create([
            'instance_id' => $instance->id,
            'stadium_id' => $stadium->id,
            'category_id' => $category->id,
            'size' => CommercialVenueSize::LARGE,
        ]);

        $this->assertFalse($venue->timestamps);
        $this->assertSame(CommercialVenueSize::LARGE, $venue->size);
        $this->assertSame($stadium->id, $venue->stadium->id);
        $this->assertSame($category->id, $venue->category->id);
    }
}
