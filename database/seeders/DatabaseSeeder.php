<?php

use App\GameEngine\GameCreation\CreateGame;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $path = 'database/base_countries.sql';
        DB::unprepared(file_get_contents($path));

        $path = 'database/base_cities.sql';
        DB::unprepared(file_get_contents($path));

        $path = 'database/base_clubs.sql';
        DB::unprepared(file_get_contents($path));

        $path = 'database/base_stadiums.sql';
        DB::unprepared(file_get_contents($path));

        $path = 'database/base_competitions.sql';
        DB::unprepared(file_get_contents($path));

        $path = 'database/positions.sql';
        DB::unprepared(file_get_contents($path));

        $path = 'database/competition_hierarchy.sql';
        DB::unprepared(file_get_contents($path));

        if (env('APP_ENV') == 'local') {
            $gameInstance = new CreateGame(
                1
            );

            $gameInstance->startNewGame();
        }
    }
}
