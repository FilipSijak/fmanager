<?php

namespace Database\Seeders;

use App\Services\StadiumService\StadiumType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StadiumConstructionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('base_stadium_expansion_costs')->insert([
            ['stadium_type' => StadiumType::VILLAGE->value, 'cost_per_1000_seats' => 100000],
            ['stadium_type' => StadiumType::LOCAL->value, 'cost_per_1000_seats' => 250000],
            ['stadium_type' => StadiumType::REGIONAL->value, 'cost_per_1000_seats' => 750000],
            ['stadium_type' => StadiumType::GLOBAL->value, 'cost_per_1000_seats' => 2000000],
        ]);
    }
}
