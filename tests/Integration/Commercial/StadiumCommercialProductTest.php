<?php

namespace Tests\Integration\Commercial;

use App\Models\BaseData\BaseCommercialCategory;
use App\Models\BaseData\BaseCommercialProducts;
use App\Models\Instance;
use App\Models\Stadium;
use App\Models\StadiumCommercialProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StadiumCommercialProductTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_stores_game_specific_commercial_product_data(): void
    {
        $instance = Instance::factory()->create();
        $stadium = Stadium::factory()->create(['instance_id' => $instance->id]);
        $category = BaseCommercialCategory::query()->forceCreate(['slug' => 'bar', 'name' => 'Bar']);
        $baseProduct = BaseCommercialProducts::query()->forceCreate(['slug' => 'draft-lager', 'category_id' => $category->id, 'name' => 'Draft Lager', 'description' => 'Cold one, on tap', 'base_price' => 450]);

        $product = StadiumCommercialProduct::factory()->create(['instance_id' => $instance->id, 'stadium_id' => $stadium->id, 'base_product_id' => $baseProduct->id, 'base_price' => 450, 'price_change_coef' => 1.1]);

        $this->assertFalse($product->timestamps);
        $this->assertSame($stadium->id, $product->stadium->id);
        $this->assertSame($baseProduct->id, $product->baseProduct->id);
        $this->assertSame('1.1000', $product->price_change_coef);
    }
}
