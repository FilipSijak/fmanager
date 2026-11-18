<?php

namespace Tests\Integration\Commercial;

use App\Models\BaseData\BaseCommercialCategory;
use App\Models\BaseData\BaseCommercialProducts;
use App\Models\Instance;
use App\Models\Stadium;
use App\Models\StadiumCommercialProduct;
use App\Models\StadiumCommercialVenue;
use App\Services\CommercialService\CommercialService;
use Database\Seeders\CommercialProductsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CommercialProductsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_seeds_the_base_commercial_product_catalog(): void
    {
        (new CommercialProductsSeeder)->run();

        $this->assertSame(10, BaseCommercialCategory::query()->count());
        $this->assertSame(22, DB::table('base_commercial_category_stadium_type')->count());
        $this->assertSame(30, DB::table('base_commercial_venue_costs')->count());
        $this->assertSame(32, BaseCommercialProducts::query()->count());
        $this->assertSame(0, BaseCommercialCategory::query()->doesntHave('products')->count());
        $this->assertSame(6, BaseCommercialProducts::query()->where('category_id', BaseCommercialCategory::query()->where('slug', 'bar')->value('id'))->count());
        $this->assertSame(6, BaseCommercialProducts::query()->where('category_id', BaseCommercialCategory::query()->where('slug', 'restaurant')->value('id'))->count());
        $this->assertSame(6, BaseCommercialProducts::query()->where('category_id', BaseCommercialCategory::query()->where('slug', 'shop')->value('id'))->count());
        $this->assertSame(450, BaseCommercialProducts::query()->where('slug', 'draft-lager')->value('base_price'));
    }

    #[Test]
    public function reseeding_fills_missing_category_products_and_existing_venues_without_overwriting_settings(): void
    {
        $this->seed(CommercialProductsSeeder::class);
        $bar = BaseCommercialCategory::query()->where('slug', 'bar')->sole();
        $cafe = BaseCommercialCategory::query()->where('slug', 'cafe')->sole();
        $baseProduct = BaseCommercialProducts::query()->where('slug', 'draft-lager')->sole();
        $bar->forceFill(['is_active' => false])->save();
        $baseProduct->forceFill(['base_price' => 600])->save();
        DB::table('base_commercial_venue_costs')->where('category_id', $bar->id)->where('size', 1)->update(['base_cost' => 123456]);
        BaseCommercialProducts::query()->whereIn('category_id', BaseCommercialCategory::query()->whereNotIn('slug', ['bar', 'shop', 'restaurant'])->select('id'))->delete();
        $instance = Instance::factory()->create();
        $stadium = Stadium::factory()->create(['instance_id' => $instance->id, 'ticket_price' => 73]);
        foreach ([$bar, $cafe] as $category) {
            StadiumCommercialVenue::factory()->create(['instance_id' => $instance->id, 'stadium_id' => $stadium->id, 'category_id' => $category->id, 'size' => 1]);
        }
        $product = StadiumCommercialProduct::factory()->create(['instance_id' => $instance->id, 'stadium_id' => $stadium->id,
            'base_product_id' => $baseProduct->id, 'base_price' => 700, 'price_change_coef' => 1.7, 'is_available' => false]);

        $this->seed(CommercialProductsSeeder::class);
        app(CommercialService::class)->initializeStadium($stadium);

        $this->assertSame(32, BaseCommercialProducts::query()->count());
        $this->assertSame(10, BaseCommercialCategory::query()->count());
        $this->assertSame(22, DB::table('base_commercial_category_stadium_type')->count());
        $this->assertSame(30, DB::table('base_commercial_venue_costs')->count());
        $this->assertSame(600, $baseProduct->fresh()->base_price);
        $this->assertFalse($bar->fresh()->is_active);
        $this->assertDatabaseHas('base_commercial_venue_costs', ['category_id' => $bar->id, 'size' => 1, 'base_cost' => 123456]);
        $this->assertSame(8, StadiumCommercialProduct::query()->forStadium($stadium)->count());
        $this->assertSame(700, $product->fresh()->base_price);
        $this->assertSame('1.7000', $product->fresh()->price_change_coef);
        $this->assertFalse($product->fresh()->is_available);
        $this->assertSame(73, $stadium->fresh()->ticket_price);
    }
}
