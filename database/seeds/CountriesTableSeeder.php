<?php

use Illuminate\Database\Seeder;

use App\GameEngine\GameData\Countries;

class CountriesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        if (DB::table('countries')->get()->count() == 0) {

            $records = [];

            foreach (Countries::countries() as $country) {
                $records[] = [
                    'name' => $country['name'],
                    'ranking' => $country['ranking'],
                    'quality' => $country['quality'],
                    'population' => $country['population']
                ];
            }

            DB::table('countries')->insert($records);

        } else { echo "\e[31mTable is not empty, therefore NOT "; }
    }
}
