<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InjuryTypesSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['type' => 'Bruising', 'severity' => 1, 'duration_from' => 1, 'duration_to' => 5],
            ['type' => 'Muscle strain', 'severity' => 2, 'duration_from' => 6, 'duration_to' => 14],
            ['type' => 'Ankle sprain', 'severity' => 3, 'duration_from' => 15, 'duration_to' => 28],
        ] as $injury) {
            if (! DB::table('injuries')->where('type', $injury['type'])->exists()) {
                DB::table('injuries')->insert($injury);
            }
        }
    }
}
