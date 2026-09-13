<?php

namespace Database\Factories;

use App\Models\Instance;
use App\Models\Stadium;
use App\Models\StadiumCommercialVenue;
use App\Services\CommercialService\CommercialVenueSize;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StadiumCommercialVenue> */
class StadiumCommercialVenueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'instance_id' => Instance::factory(),
            'stadium_id' => Stadium::factory(),
            'category_id' => 1,
            'size' => CommercialVenueSize::SMALL->value,
        ];
    }
}
