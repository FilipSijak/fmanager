<?php

namespace Tests\Integration\Services\StadiumService;

use App\Models\BaseData\BaseCommercialCategory;
use App\Models\Country;
use App\Models\Instance;
use App\Models\Stadium;
use App\Models\StadiumCommercialVenue;
use App\Models\StadiumStand;
use App\Services\CommercialService\CommercialVenueSize;
use App\Services\StadiumService\StadiumService;
use App\Services\StadiumService\StadiumType;
use App\StadiumStandPosition;
use App\StadiumStandStatus;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StadiumServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_builds_an_available_commercial_venue_within_the_stadium_limit(): void
    {
        $instance = Instance::factory()->create();
        $stadium = Stadium::factory()->create([
            'instance_id' => $instance->id,
            'type' => StadiumType::LOCAL,
            'commercial_limit' => 1,
            'country_code' => 'GBR',
        ]);
        Country::query()->forceCreate([
            'code' => 'GBR',
            'name' => 'United Kingdom',
            'ranking' => 100,
            'population' => 60000000,
        ]);
        $category = BaseCommercialCategory::query()->forceCreate(['slug' => 'bar', 'name' => 'Bar']);
        $this->mapCategoryToStadiumType($category->id, StadiumType::LOCAL);

        $venue = app(StadiumService::class)->buildCommercialVenue(
            $stadium,
            $category->id,
            CommercialVenueSize::MEDIUM,
        );

        $this->assertInstanceOf(StadiumCommercialVenue::class, $venue);
        $this->assertSame(CommercialVenueSize::MEDIUM, $venue->size);
        $this->assertDatabaseHas('stadium_commercial_venues', [
            'instance_id' => $instance->id,
            'stadium_id' => $stadium->id,
            'category_id' => $category->id,
            'size' => CommercialVenueSize::MEDIUM->value,
        ]);
    }

    #[Test]
    public function it_rejects_a_commercial_category_unavailable_for_the_stadium_type(): void
    {
        $instance = Instance::factory()->create();
        $stadium = Stadium::factory()->create([
            'instance_id' => $instance->id,
            'type' => StadiumType::VILLAGE,
        ]);
        $category = BaseCommercialCategory::query()->forceCreate(['slug' => 'casino', 'name' => 'Casino']);
        $this->mapCategoryToStadiumType($category->id, StadiumType::GLOBAL);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('This commercial category is not available for the stadium type.');

        app(StadiumService::class)->buildCommercialVenue($stadium, $category->id, CommercialVenueSize::SMALL);
    }

    #[Test]
    public function it_rejects_a_commercial_venue_when_the_stadium_limit_is_reached(): void
    {
        $instance = Instance::factory()->create();
        $stadium = Stadium::factory()->create([
            'instance_id' => $instance->id,
            'type' => StadiumType::LOCAL,
            'commercial_limit' => 1,
        ]);
        $existingCategory = BaseCommercialCategory::query()->forceCreate(['slug' => 'bar', 'name' => 'Bar']);
        $newCategory = BaseCommercialCategory::query()->forceCreate(['slug' => 'cafe', 'name' => 'Cafe']);
        $this->mapCategoryToStadiumType($existingCategory->id, StadiumType::LOCAL);
        $this->mapCategoryToStadiumType($newCategory->id, StadiumType::LOCAL);
        StadiumCommercialVenue::query()->create([
            'instance_id' => $instance->id,
            'stadium_id' => $stadium->id,
            'category_id' => $existingCategory->id,
            'size' => CommercialVenueSize::SMALL,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('The stadium has reached its commercial venue limit.');

        app(StadiumService::class)->buildCommercialVenue($stadium, $newCategory->id, CommercialVenueSize::LARGE);
    }

    #[Test]
    public function it_rejects_building_the_same_commercial_category_twice(): void
    {
        $instance = Instance::factory()->create();
        $stadium = Stadium::factory()->create([
            'instance_id' => $instance->id,
            'type' => StadiumType::LOCAL,
        ]);
        $category = BaseCommercialCategory::query()->forceCreate(['slug' => 'bar', 'name' => 'Bar']);
        $this->mapCategoryToStadiumType($category->id, StadiumType::LOCAL);
        StadiumCommercialVenue::query()->create([
            'instance_id' => $instance->id,
            'stadium_id' => $stadium->id,
            'category_id' => $category->id,
            'size' => CommercialVenueSize::SMALL,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('This commercial venue category has already been built at the stadium.');

        app(StadiumService::class)->buildCommercialVenue($stadium, $category->id, CommercialVenueSize::LARGE);
    }

    #[Test]
    public function it_demolishes_a_venue_and_returns_a_rounded_demolition_cost(): void
    {
        $instance = Instance::factory()->create();
        $stadium = Stadium::factory()->create(['instance_id' => $instance->id]);
        $category = BaseCommercialCategory::query()->forceCreate(['slug' => 'bar', 'name' => 'Bar']);
        $this->mapCategoryToStadiumType($category->id, StadiumType::LOCAL);
        $venue = StadiumCommercialVenue::query()->create([
            'instance_id' => $instance->id,
            'stadium_id' => $stadium->id,
            'category_id' => $category->id,
            'size' => CommercialVenueSize::LARGE,
            'build_cost' => 845000,
        ]);

        $demolitionCost = app(StadiumService::class)->demolishCommercialVenue($stadium, $venue->id);

        $this->assertSame(85000, $demolitionCost);
        $this->assertDatabaseMissing('stadium_commercial_venues', ['id' => $venue->id]);
    }

    #[Test]
    public function it_rejects_demolishing_a_venue_that_does_not_belong_to_the_stadium(): void
    {
        $instance = Instance::factory()->create();
        $stadium = Stadium::factory()->create(['instance_id' => $instance->id]);
        $otherStadium = Stadium::factory()->create(['instance_id' => $instance->id]);
        $category = BaseCommercialCategory::query()->forceCreate(['slug' => 'bar', 'name' => 'Bar']);
        $this->mapCategoryToStadiumType($category->id, StadiumType::LOCAL);
        $venue = StadiumCommercialVenue::query()->create([
            'instance_id' => $instance->id,
            'stadium_id' => $otherStadium->id,
            'category_id' => $category->id,
            'size' => CommercialVenueSize::SMALL,
            'build_cost' => 250000,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('This commercial venue does not exist at the stadium.');

        app(StadiumService::class)->demolishCommercialVenue($stadium, $venue->id);
    }

    #[Test]
    public function it_lists_built_venues_with_their_categories(): void
    {
        $instance = Instance::factory()->create();
        $stadium = Stadium::factory()->create(['instance_id' => $instance->id, 'type' => StadiumType::LOCAL]);
        $category = BaseCommercialCategory::query()->forceCreate(['slug' => 'bar', 'name' => 'Bar']);
        $this->mapCategoryToStadiumType($category->id, StadiumType::LOCAL);
        StadiumCommercialVenue::query()->create(['instance_id' => $instance->id, 'stadium_id' => $stadium->id, 'category_id' => $category->id, 'size' => CommercialVenueSize::MEDIUM]);

        $venues = app(StadiumService::class)->commercialVenuesForStadium($stadium);

        $this->assertCount(1, $venues);
        $this->assertSame($category->id, $venues->first()->category->id);
        $this->assertSame(CommercialVenueSize::MEDIUM, $venues->first()->size);
    }

    #[Test]
    public function it_lists_only_active_and_unbuilt_categories_available_to_the_stadium(): void
    {
        $instance = Instance::factory()->create();
        $stadium = Stadium::factory()->create(['instance_id' => $instance->id, 'type' => StadiumType::LOCAL, 'commercial_limit' => 3]);
        $built = BaseCommercialCategory::query()->forceCreate(['slug' => 'bar', 'name' => 'Bar']);
        $available = BaseCommercialCategory::query()->forceCreate(['slug' => 'cafe', 'name' => 'Cafe']);
        $inactive = BaseCommercialCategory::query()->forceCreate(['slug' => 'shop', 'name' => 'Shop', 'is_active' => false]);
        $unavailable = BaseCommercialCategory::query()->forceCreate(['slug' => 'casino', 'name' => 'Casino']);
        foreach ([$built, $available, $inactive] as $category) {
            $this->mapCategoryToStadiumType($category->id, StadiumType::LOCAL);
        }
        $this->mapCategoryToStadiumType($unavailable->id, StadiumType::GLOBAL);
        StadiumCommercialVenue::query()->create(['instance_id' => $instance->id, 'stadium_id' => $stadium->id, 'category_id' => $built->id, 'size' => CommercialVenueSize::SMALL]);

        $categories = app(StadiumService::class)->buildableCommercialCategoriesForStadium($stadium);

        $this->assertSame(['Cafe'], $categories->pluck('name')->all());
    }

    #[Test]
    public function it_lists_no_buildable_categories_when_the_stadium_limit_is_reached(): void
    {
        $instance = Instance::factory()->create();
        $stadium = Stadium::factory()->create(['instance_id' => $instance->id, 'type' => StadiumType::LOCAL, 'commercial_limit' => 1]);
        $category = BaseCommercialCategory::query()->forceCreate(['slug' => 'bar', 'name' => 'Bar']);
        $this->mapCategoryToStadiumType($category->id, StadiumType::LOCAL);
        StadiumCommercialVenue::query()->create(['instance_id' => $instance->id, 'stadium_id' => $stadium->id, 'category_id' => $category->id, 'size' => CommercialVenueSize::SMALL]);

        $categories = app(StadiumService::class)->buildableCommercialCategoriesForStadium($stadium);

        $this->assertCount(0, $categories);
    }

    #[Test]
    public function it_accepts_a_stadium_build_when_stand_capacities_are_within_type_limits(): void
    {
        $instance = Instance::factory()->create();
        $stadium = Stadium::factory()->create(['instance_id' => $instance->id, 'type' => StadiumType::LOCAL, 'capacity' => 0, 'active_capacity' => 0]);
        StadiumStand::factory()->create(['stadium_id' => $stadium->id, 'position' => StadiumStandPosition::NORTH, 'capacity' => 1000, 'status' => StadiumStandStatus::ACTIVE]);
        StadiumStand::factory()->create(['stadium_id' => $stadium->id, 'position' => StadiumStandPosition::NORTH_EAST, 'capacity' => 2000, 'status' => StadiumStandStatus::UNDER_CONSTRUCTION]);
        $stadium->refresh();

        app(StadiumService::class)->validateStadiumBuild($stadium);

        $this->assertSame(5000, app(StadiumService::class)->maximumCapacityForStadium($stadium));
    }

    #[Test]
    public function it_rejects_a_stadium_build_above_the_type_capacity(): void
    {
        $stadium = Stadium::factory()->create(['instance_id' => Instance::factory()->create()->id, 'type' => StadiumType::VILLAGE, 'capacity' => 0, 'active_capacity' => 0]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Stadium capacity exceeds the maximum for its stadium type.');

        app(StadiumService::class)->validateStadiumCapacity($stadium, 1001);
    }

    #[Test]
    public function it_rejects_a_stadium_build_when_stored_capacities_do_not_match_its_stands(): void
    {
        $stadium = Stadium::factory()->create(['instance_id' => Instance::factory()->create()->id, 'type' => StadiumType::LOCAL, 'capacity' => 0, 'active_capacity' => 0]);
        StadiumStand::factory()->create(['stadium_id' => $stadium->id, 'position' => StadiumStandPosition::NORTH, 'capacity' => 1000, 'status' => StadiumStandStatus::ACTIVE]);
        $stadium->forceFill(['capacity' => 999, 'active_capacity' => 1000])->saveQuietly();
        $stadium->refresh();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Stadium capacity does not match its stands.');

        app(StadiumService::class)->validateStadiumBuild($stadium);
    }

    #[Test]
    public function it_rejects_a_negative_stadium_capacity(): void
    {
        $stadium = Stadium::factory()->create(['instance_id' => Instance::factory()->create()->id, 'type' => StadiumType::VILLAGE]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Stadium capacity cannot be negative.');

        app(StadiumService::class)->validateStadiumCapacity($stadium, -1);
    }

    private function mapCategoryToStadiumType(int $categoryId, StadiumType $stadiumType): void
    {
        DB::table('base_commercial_category_stadium_type')->insert([
            'category_id' => $categoryId,
            'stadium_type' => $stadiumType->value,
        ]);

        foreach (CommercialVenueSize::cases() as $size) {
            DB::table('base_commercial_venue_costs')->insert([
                'category_id' => $categoryId,
                'size' => $size->value,
                'base_cost' => 100000,
            ]);
        }
    }
}
