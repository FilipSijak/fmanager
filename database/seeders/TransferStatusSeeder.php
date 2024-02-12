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
        ];

        DB::table('transfer_status')->insert($transferStatusOptions);
    }
}
