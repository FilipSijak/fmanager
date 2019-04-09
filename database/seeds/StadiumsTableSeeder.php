<?php

use Illuminate\Database\Seeder;

use App\GameEngine\GameData\Stadiums;

class StadiumsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        if (DB::table('stadiums')->get()->count() == 0) {

            $records = [];

            foreach (Stadiums::stadiums() as $stadium) {
                $records[] = [
                    'name' => $stadium['name'],
                    'capacity' => $stadium['capacity'],
                    'city_id' => $stadium['city_id'],
                    'country_id' => $stadium['country_id'],
                    'club_id' => $stadium['club_id']
                ];
            }

            DB::table('stadiums')->insert($records);

        } else { echo "\e[31mTable is not empty, therefore NOT "; }
    }
}
