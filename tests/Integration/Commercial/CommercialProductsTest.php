<?php

namespace Tests\Integration\Commercial;

use App\Models\BaseData\BaseCommercialProducts;
use Database\Seeders\CommercialProductsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CommercialProductsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_seeds_the_base_commercial_product_catalog(): void
    {
        (new CommercialProductsSeeder)->run();

        $this->assertSame(18, BaseCommercialProducts::query()->count());
        $this->assertSame(6, BaseCommercialProducts::query()->where('category', 'bar')->count());
        $this->assertSame(6, BaseCommercialProducts::query()->where('category', 'restaurant')->count());
        $this->assertSame(6, BaseCommercialProducts::query()->where('category', 'shop')->count());
        $this->assertSame(450, BaseCommercialProducts::query()->where('slug', 'draft-lager')->value('base_price'));
    }
}
