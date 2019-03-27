<?php

use Illuminate\Database\Seeder;
use App\GameEngine\Player\PlayerConfiguration\PlayerPositionConfig;

class PositionsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // check if table users is empty
        if(DB::table('positions')->get()->count() == 0){

            $records = [];

            foreach (PlayerPositionConfig::PLAYER_POSITIONS as $position) {
                $records[] = ['name' => $position];
            }

            DB::table('positions')->insert($records);

        } else { echo "\e[31mTable is not empty, therefore NOT "; }
    }
}
