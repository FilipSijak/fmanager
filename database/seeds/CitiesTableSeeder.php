<?php

use Illuminate\Database\Seeder;

use App\GameEngine\GameData\Cities;

class CitiesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        if (DB::table('cities')->get()->count() == 0) {

            $records = [];

            foreach (Cities::cities() as $city) {
                $records[] = [
                    'name' => $city['name'],
                    'population' => $city['population'],
                    'country_id' => $city['country_id']
                ];
            }

            DB::table('cities')->insert($records);

        } else { echo "\e[31mTable is not empty, therefore NOT "; }
    }
}
