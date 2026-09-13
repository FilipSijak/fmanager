<?php

namespace Tests\Integration\Services\StadiumService;

use App\Models\BaseData\BaseCommercialCategory;
use App\Models\Instance;
use App\Models\Stadium;
use App\Models\StadiumCommercialVenue;
use App\Services\CommercialService\CommercialVenueSize;
use App\Services\StadiumService\StadiumService;
use App\Services\StadiumService\StadiumType;
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

    private function mapCategoryToStadiumType(int $categoryId, StadiumType $stadiumType): void
    {
        DB::table('base_commercial_category_stadium_type')->insert([
            'category_id' => $categoryId,
            'stadium_type' => $stadiumType->value,
        ]);
    }
}
