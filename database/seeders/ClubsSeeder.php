<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClubsSeeder extends Seeder
{
    public function run()
    {
        $path = 'database/base_clubs.sql';
        DB::unprepared(file_get_contents($path));
    }
}
