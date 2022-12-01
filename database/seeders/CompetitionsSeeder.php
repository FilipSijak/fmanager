<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompetitionsSeeder extends Seeder
{
    public function run()
    {
        $path = 'database/base_competitions.sql';
        DB::unprepared(file_get_contents($path));
    }
}
