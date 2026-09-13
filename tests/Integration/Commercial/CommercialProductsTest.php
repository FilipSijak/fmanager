<?php

namespace Tests\Integration\Commercial;

use App\Models\BaseData\BaseCommercialCategory;
use App\Models\BaseData\BaseCommercialProducts;
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

        $this->assertSame(8, BaseCommercialCategory::query()->count());
        $this->assertSame(22, DB::table('base_commercial_category_stadium_type')->count());
        $this->assertSame(18, BaseCommercialProducts::query()->count());
        $this->assertSame(6, BaseCommercialProducts::query()->where('category_id', BaseCommercialCategory::query()->where('slug', 'bar')->value('id'))->count());
        $this->assertSame(6, BaseCommercialProducts::query()->where('category_id', BaseCommercialCategory::query()->where('slug', 'restaurant')->value('id'))->count());
        $this->assertSame(6, BaseCommercialProducts::query()->where('category_id', BaseCommercialCategory::query()->where('slug', 'shop')->value('id'))->count());
        $this->assertSame(450, BaseCommercialProducts::query()->where('slug', 'draft-lager')->value('base_price'));
    }
}
