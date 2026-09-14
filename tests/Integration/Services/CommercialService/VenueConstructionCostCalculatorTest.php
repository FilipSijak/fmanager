<?php

namespace Tests\Integration\Services\CommercialService;

use App\Models\BaseData\BaseCommercialCategory;
use App\Models\Country;
use App\Models\Instance;
use App\Models\Stadium;
use App\Services\CommercialService\CommercialVenueSize;
use App\Services\CommercialService\VenueConstructionCostCalculator;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VenueConstructionCostCalculatorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_applies_the_country_premium_to_the_category_and_size_cost(): void
    {
        $country = Country::query()->forceCreate([
            'code' => 'GBR',
            'name' => 'United Kingdom',
            'ranking' => 100,
            'population' => 60000000,
        ]);
        $stadium = Stadium::factory()->create(['instance_id' => Instance::factory()->create()->id, 'country_code' => $country->code]);
        $category = BaseCommercialCategory::query()->forceCreate(['slug' => 'shop', 'name' => 'Shop']);
        $category->venueCosts()->create(['size' => CommercialVenueSize::MEDIUM, 'base_cost' => 400000]);

        $cost = app(VenueConstructionCostCalculator::class)->calculate($stadium, $category, CommercialVenueSize::MEDIUM);

        $this->assertSame(600000, $cost);
    }

    #[Test]
    public function it_rejects_a_stadium_without_a_ranked_country(): void
    {
        $stadium = Stadium::factory()->create(['instance_id' => Instance::factory()->create()->id, 'country_code' => null]);
        $category = BaseCommercialCategory::query()->forceCreate(['slug' => 'shop', 'name' => 'Shop']);
        $category->venueCosts()->create(['size' => CommercialVenueSize::SMALL, 'base_cost' => 250000]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('The stadium country does not have a ranking for venue construction.');

        app(VenueConstructionCostCalculator::class)->calculate($stadium, $category, CommercialVenueSize::SMALL);
    }
}
