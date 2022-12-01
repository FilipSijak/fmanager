<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StadiumSeeder extends Seeder
{
    public function run()
    {
        $path = 'database/base_stadiums.sql';
        DB::unprepared(file_get_contents($path));
    }
}
