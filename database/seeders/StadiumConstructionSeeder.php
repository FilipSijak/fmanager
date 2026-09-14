<?php

namespace Database\Seeders;

use App\Services\StadiumService\StadiumType;
use App\StadiumStandPosition;
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
}
