<?php

namespace Database\Seeders;

/*use App\GameEngine\GameCreation\CreateGame;*/
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $path = 'database/countries.sql';
        DB::unprepared(file_get_contents($path));

        $path = 'database/cities.sql';
        DB::unprepared(file_get_contents($path));

        (new ClubsSeeder)->run();
        (new StadiumSeeder)->run();
        (new CompetitionsSeeder)->run();
        (new TransferStatusSeeder)->run();

        $path = 'database/positions.sql';
        DB::unprepared(file_get_contents($path));

        $path = 'database/competition_hierarchy.sql';
        DB::unprepared(file_get_contents($path));

/*        if (env('APP_ENV') == 'local') {
            $gameInstance = new CreateGame(
                1
            );

            $gameInstance->startNewGame();
        }*/
    }
}
