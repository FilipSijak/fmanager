<?php

namespace Database\Factories;

use App\Models\Instance;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Season>
 */
class SeasonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'instance_id' => Instance::factory()->make(['id'])->id,
            'start_date' => Carbon::createFromFormat('d/m/Y',  '25/08/' . date('Y')),
            'end_date' => Carbon::createFromFormat('d/m/Y',  '28/08/' . date('Y'))
        ];
    }
}
