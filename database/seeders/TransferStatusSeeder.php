<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TransferStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $transferStatusOptions = [
            ['id' => 1, 'status' => 'WAITING_TARGET_CLUB'],
            ['id' => 2, 'status' => 'WAITING_SOURCE_CLUB'],
            ['id' => 3, 'status' => 'WAITING_PLAYER'],
            ['id' => 4, 'status' => 'WAITING_PAPERWORK'],
            ['id' => 5, 'status' => 'WAITING_TRANSFER_WINDOW'],
            ['id' => 6, 'status' => 'TRANSFER_COMPLETED'],
            ['id' => 7, 'status' => 'TRANSFER_FAILED'],
            ['id' => 8, 'status' => 'TARGET_CLUB_APPROVED'],
            ['id' => 9, 'status' => 'SOURCE_CLUB_APPROVED'],
            ['id' => 10, 'status' => 'PLAYER_APPROVED'],
        ];

        DB::table('transfer_status')->insert($transferStatusOptions);

        $transferTypes = [
            ['id' => 1, 'type' => 'FREE_TRANSFER'],
            ['id' => 2, 'type' => 'LOAN_TRANSFER'],
            ['id' => 3, 'type' => 'PERMANENT_TRANSFER'],
        ];

        DB::table('transfer_types')->insert($transferTypes);
    }


}
