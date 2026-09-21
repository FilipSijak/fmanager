<?php

namespace Tests\Integration\Services\StadiumService;

use App\Events\NextDay;
use App\Listeners\CompleteStadiumStandConstruction;
use App\Models\BaseData\BaseCommercialCategory;
use App\Models\BaseData\BaseStadiumExpansionCost;
use App\Models\Country;
use App\Models\Instance;
use App\Models\Stadium;
use App\Models\StadiumCommercialVenue;
use App\Models\StadiumStand;
use App\Models\StadiumStandConstruction;
use App\Services\CommercialService\CommercialVenueSize;
use App\Services\StadiumService\StadiumService;
use App\Services\StadiumService\StadiumType;
use App\StadiumStandPosition;
use App\StadiumStandStatus;
use Carbon\CarbonImmutable;
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

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('base_stadium_stand_capacity_limits')->insert([
            ['stadium_type' => StadiumType::VILLAGE->value, 'position' => StadiumStandPosition::NORTH->value, 'maximum_capacity' => 1000],
            ['stadium_type' => StadiumType::LOCAL->value, 'position' => StadiumStandPosition::NORTH->value, 'maximum_capacity' => 7000],
            ['stadium_type' => StadiumType::LOCAL->value, 'position' => StadiumStandPosition::EAST->value, 'maximum_capacity' => 3000],
            ['stadium_type' => StadiumType::LOCAL->value, 'position' => StadiumStandPosition::SOUTH->value, 'maximum_capacity' => 7000],
            ['stadium_type' => StadiumType::LOCAL->value, 'position' => StadiumStandPosition::WEST->value, 'maximum_capacity' => 3000],
            ['stadium_type' => StadiumType::REGIONAL->value, 'position' => StadiumStandPosition::NORTH->value, 'maximum_capacity' => 14000],
            ['stadium_type' => StadiumType::REGIONAL->value, 'position' => StadiumStandPosition::EAST->value, 'maximum_capacity' => 6000],
            ['stadium_type' => StadiumType::REGIONAL->value, 'position' => StadiumStandPosition::SOUTH->value, 'maximum_capacity' => 14000],
            ['stadium_type' => StadiumType::REGIONAL->value, 'position' => StadiumStandPosition::WEST->value, 'maximum_capacity' => 6000],
            ['stadium_type' => StadiumType::REGIONAL->value, 'position' => StadiumStandPosition::NORTH_EAST->value, 'maximum_capacity' => 5000],
            ['stadium_type' => StadiumType::REGIONAL->value, 'position' => StadiumStandPosition::SOUTH_EAST->value, 'maximum_capacity' => 5000],
            ['stadium_type' => StadiumType::REGIONAL->value, 'position' => StadiumStandPosition::SOUTH_WEST->value, 'maximum_capacity' => 5000],
            ['stadium_type' => StadiumType::REGIONAL->value, 'position' => StadiumStandPosition::NORTH_WEST->value, 'maximum_capacity' => 5000],
            ['stadium_type' => StadiumType::GLOBAL->value, 'position' => StadiumStandPosition::NORTH->value, 'maximum_capacity' => 20000],
            ['stadium_type' => StadiumType::GLOBAL->value, 'position' => StadiumStandPosition::EAST->value, 'maximum_capacity' => 12000],
            ['stadium_type' => StadiumType::GLOBAL->value, 'position' => StadiumStandPosition::SOUTH->value, 'maximum_capacity' => 20000],
            ['stadium_type' => StadiumType::GLOBAL->value, 'position' => StadiumStandPosition::WEST->value, 'maximum_capacity' => 12000],
            ['stadium_type' => StadiumType::GLOBAL->value, 'position' => StadiumStandPosition::NORTH_EAST->value, 'maximum_capacity' => 9000],
            ['stadium_type' => StadiumType::GLOBAL->value, 'position' => StadiumStandPosition::SOUTH_EAST->value, 'maximum_capacity' => 9000],
            ['stadium_type' => StadiumType::GLOBAL->value, 'position' => StadiumStandPosition::SOUTH_WEST->value, 'maximum_capacity' => 9000],
            ['stadium_type' => StadiumType::GLOBAL->value, 'position' => StadiumStandPosition::NORTH_WEST->value, 'maximum_capacity' => 9000],
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
    public function it_calculates_country_adjusted_expansion_cost_per_1000_seats(): void
    {
        $instance = Instance::factory()->create();
        Country::query()->forceCreate(['code' => 'GBR', 'name' => 'United Kingdom', 'ranking' => 100, 'population' => 60000000]);
        $stadium = Stadium::factory()->create(['instance_id' => $instance->id, 'country_code' => 'GBR', 'type' => StadiumType::LOCAL, 'capacity' => 4000]);
        BaseStadiumExpansionCost::query()->create(['stadium_type' => StadiumType::LOCAL, 'cost_per_1000_seats' => 40000]);

        $cost = app(StadiumService::class)->stadiumExpansionCost($stadium, 1000);

        $this->assertSame(60000, $cost);
    }

    #[Test]
    public function it_rejects_an_expansion_above_the_stadium_type_capacity(): void
    {
        $stadium = Stadium::factory()->create(['instance_id' => Instance::factory()->create()->id, 'type' => StadiumType::LOCAL, 'capacity' => 20000]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Stadium expansion exceeds the maximum capacity for its type.');

        app(StadiumService::class)->stadiumExpansionCost($stadium, 1);
    }

    #[Test]
    public function it_rejects_a_non_positive_expansion(): void
    {
        $stadium = Stadium::factory()->create(['instance_id' => Instance::factory()->create()->id, 'type' => StadiumType::LOCAL, 'capacity' => 4000]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Stadium expansion capacity must be greater than zero.');

        app(StadiumService::class)->stadiumExpansionCost($stadium, 0);
    }

    #[Test]
    public function it_rejects_corner_stands_for_village_and_local_stadiums(): void
    {
        $stadium = Stadium::factory()->create(['instance_id' => Instance::factory()->create()->id, 'type' => StadiumType::LOCAL]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Village and Local stadiums cannot build corner stands.');

        app(StadiumService::class)->validateStadiumStandPosition($stadium, StadiumStandPosition::NORTH_EAST);
    }

    #[Test]
    public function it_allows_one_stand_for_village_four_for_local_and_eight_for_global_stadiums(): void
    {
        $villageStadium = Stadium::factory()->create(['instance_id' => Instance::factory()->create()->id, 'type' => StadiumType::VILLAGE]);
        $localStadium = Stadium::factory()->create(['instance_id' => Instance::factory()->create()->id, 'type' => StadiumType::LOCAL]);
        $globalStadium = Stadium::factory()->create(['instance_id' => Instance::factory()->create()->id, 'type' => StadiumType::GLOBAL]);

        $service = app(StadiumService::class);

        $this->assertSame(1, $service->maximumStandsForStadium($villageStadium));
        $this->assertSame(4, $service->maximumStandsForStadium($localStadium));
        $this->assertSame(8, $service->maximumStandsForStadium($globalStadium));
    }

    #[Test]
    public function it_rejects_non_north_stands_for_a_village_stadium(): void
    {
        $stadium = Stadium::factory()->create(['instance_id' => Instance::factory()->create()->id, 'type' => StadiumType::VILLAGE]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('This stand position is not available for the stadium type.');

        app(StadiumService::class)->validateStadiumStandPosition($stadium, StadiumStandPosition::EAST);
    }

    #[Test]
    public function it_rejects_a_stand_above_its_position_capacity(): void
    {
        $stadium = Stadium::factory()->create(['instance_id' => Instance::factory()->create()->id, 'type' => StadiumType::LOCAL, 'capacity' => 0, 'active_capacity' => 0]);
        StadiumStand::factory()->create(['stadium_id' => $stadium->id, 'position' => StadiumStandPosition::NORTH, 'capacity' => 8000, 'status' => StadiumStandStatus::ACTIVE]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Stadium stand capacity exceeds the maximum for its position.');

        app(StadiumService::class)->validateStadiumBuild($stadium->refresh());
    }

    #[Test]
    public function it_rejects_a_stand_capacity_that_is_not_a_multiple_of_one_thousand(): void
    {
        $stadium = Stadium::factory()->create(['instance_id' => Instance::factory()->create()->id, 'type' => StadiumType::LOCAL, 'capacity' => 0, 'active_capacity' => 0]);
        StadiumStand::factory()->create(['stadium_id' => $stadium->id, 'position' => StadiumStandPosition::NORTH, 'capacity' => 1500, 'status' => StadiumStandStatus::ACTIVE]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Stadium stand capacity must be a multiple of 1,000 seats.');

        app(StadiumService::class)->validateStadiumBuild($stadium->refresh());
    }

    #[Test]
    public function it_calculates_one_week_of_construction_per_thousand_seats(): void
    {
        $service = app(StadiumService::class);

        $this->assertSame(10, $service->durationInWeeks(10000));
    }

    #[Test]
    public function it_starts_independent_construction_for_multiple_stands(): void
    {
        $instance = Instance::factory()->create(['instance_date' => '2026-09-14']);
        $stadium = Stadium::factory()->create(['instance_id' => $instance->id, 'type' => StadiumType::REGIONAL, 'capacity' => 0, 'active_capacity' => 0]);
        $northStand = StadiumStand::factory()->create(['stadium_id' => $stadium->id, 'position' => StadiumStandPosition::NORTH, 'capacity' => 0, 'status' => null]);
        $southStand = StadiumStand::factory()->create(['stadium_id' => $stadium->id, 'position' => StadiumStandPosition::SOUTH, 'capacity' => 0, 'status' => null]);
        $service = app(StadiumService::class);
        $startedAt = CarbonImmutable::parse('2026-09-14');

        $northConstruction = $service->startStandConstruction($northStand, 10000, $startedAt);
        $southConstruction = $service->startStandConstruction($southStand, 10000, $startedAt);

        $this->assertSame(10000, $northConstruction->capacity_increase);
        $this->assertSame('2026-11-23', $northConstruction->completes_at->toDateString());
        $this->assertSame('2026-11-23', $southConstruction->completes_at->toDateString());
        $this->assertDatabaseCount('stadium_stand_constructions', 2);
        $this->assertSame(StadiumStandStatus::UNDER_CONSTRUCTION, $northStand->fresh()->status);
        $this->assertSame(StadiumStandStatus::UNDER_CONSTRUCTION, $southStand->fresh()->status);
    }

    #[Test]
    public function it_recalculates_active_capacity_for_all_stadiums_with_ongoing_construction(): void
    {
        $instance = Instance::factory()->create(['instance_date' => '2026-09-14']);
        $firstStadium = Stadium::factory()->create(['instance_id' => $instance->id, 'type' => StadiumType::REGIONAL, 'capacity' => 7000, 'active_capacity' => 7000]);
        $secondStadium = Stadium::factory()->create(['instance_id' => $instance->id, 'type' => StadiumType::REGIONAL, 'capacity' => 3000, 'active_capacity' => 3000]);
        $firstStand = StadiumStand::factory()->create(['stadium_id' => $firstStadium->id, 'position' => StadiumStandPosition::NORTH, 'capacity' => 7000, 'status' => StadiumStandStatus::ACTIVE]);
        $secondStand = StadiumStand::factory()->create(['stadium_id' => $secondStadium->id, 'position' => StadiumStandPosition::NORTH, 'capacity' => 3000, 'status' => StadiumStandStatus::ACTIVE]);
        $constructionService = app(StadiumService::class);
        $startedAt = CarbonImmutable::parse('2026-09-14');
        $constructionService->startStandConstruction($firstStand, 10000, $startedAt);
        $constructionService->startStandConstruction($secondStand, 6000, $startedAt);
        $firstStadium->forceFill(['active_capacity' => 0])->saveQuietly();
        $secondStadium->forceFill(['active_capacity' => 0])->saveQuietly();

        app(CompleteStadiumStandConstruction::class)->handle(new NextDay($instance));

        $this->assertSame(0, $firstStadium->fresh()->active_capacity);
        $this->assertSame(0, $secondStadium->fresh()->active_capacity);
        $this->assertSame(10000, $firstStadium->fresh()->capacity);
        $this->assertSame(6000, $secondStadium->fresh()->capacity);
    }

    #[Test]
    public function it_completes_due_stand_construction_and_activates_the_stand(): void
    {
        $instance = Instance::factory()->create(['instance_date' => '2026-09-14']);
        $stadium = Stadium::factory()->create(['instance_id' => $instance->id, 'type' => StadiumType::REGIONAL, 'capacity' => 0, 'active_capacity' => 0]);
        $stand = StadiumStand::factory()->create(['stadium_id' => $stadium->id, 'position' => StadiumStandPosition::NORTH, 'capacity' => 0, 'status' => null]);
        $service = app(StadiumService::class);
        $construction = $service->startStandConstruction($stand, 10000, CarbonImmutable::parse('2026-09-14'));

        $completed = $service->completeStandConstructionForInstance($instance, $construction->completes_at);

        $this->assertSame(1, $completed);
        $this->assertSame(StadiumStandStatus::ACTIVE, $stand->fresh()->status);
        $this->assertSame(10000, $stadium->fresh()->capacity);
        $this->assertSame(10000, $stadium->fresh()->active_capacity);
        $this->assertDatabaseHas('stadium_stand_constructions', [
            'id' => $construction->id,
            'completed_at' => $construction->completes_at->toDateString(),
        ]);
    }

    #[Test]
    public function it_accepts_a_stadium_build_when_stand_capacities_are_within_type_limits(): void
    {
        $instance = Instance::factory()->create();
        $stadium = Stadium::factory()->create(['instance_id' => $instance->id, 'type' => StadiumType::REGIONAL, 'capacity' => 0, 'active_capacity' => 0]);
        StadiumStand::factory()->create(['stadium_id' => $stadium->id, 'position' => StadiumStandPosition::NORTH, 'capacity' => 1000, 'status' => StadiumStandStatus::ACTIVE]);
        StadiumStand::factory()->create(['stadium_id' => $stadium->id, 'position' => StadiumStandPosition::NORTH_EAST, 'capacity' => 2000, 'status' => StadiumStandStatus::UNDER_CONSTRUCTION]);
        app(StadiumService::class)->recalculateCapacities($stadium);

        app(StadiumService::class)->validateStadiumBuild($stadium);

        $this->assertSame(60000, app(StadiumService::class)->maximumCapacityForStadium($stadium));
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

    #[Test]
    public function it_rejects_building_an_inactive_commercial_category(): void
    {
        $instance = Instance::factory()->create();
        $stadium = Stadium::factory()->create([
            'instance_id' => $instance->id,
            'type' => StadiumType::LOCAL,
        ]);
        $category = BaseCommercialCategory::query()->forceCreate([
            'slug' => 'closed-bar',
            'name' => 'Closed Bar',
            'is_active' => false,
        ]);
        $this->mapCategoryToStadiumType($category->id, StadiumType::LOCAL);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('This commercial category is not available for the stadium type.');

        app(StadiumService::class)->buildCommercialVenue(
            $stadium,
            $category->id,
            CommercialVenueSize::SMALL,
        );
    }

    #[Test]
    public function it_can_instantiate_the_stadium_construction_factory(): void
    {
        $attributes = StadiumStandConstruction::factory()->raw();

        $this->assertSame(1000, $attributes['target_capacity']);
        $this->assertSame(1000, $attributes['capacity_increase']);
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
