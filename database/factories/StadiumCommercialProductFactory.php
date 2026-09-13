<?php

namespace Database\Factories;

use App\Models\Instance;
use App\Models\Stadium;
use App\Models\StadiumCommercialProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StadiumCommercialProduct>
 */
class StadiumCommercialProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'instance_id' => Instance::factory(),
            'stadium_id' => Stadium::factory(),
            'base_product_id' => 1,
            'category' => 'bar',
            'base_price' => 450,
            'price_change_coef' => 1,
            'is_available' => true,
        ];
    }
}
