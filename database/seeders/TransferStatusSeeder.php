<?php

namespace Database\Seeders;

use App\Services\TransferService\TransferStatusTypes;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TransferStatusSeeder extends Seeder
{
    public function run()
    {
        $transferStatusOptions = [];

        foreach (TransferStatusTypes::TRANSFER_STATUS_TYPES_LIST as $type) {
            $transferStatusOptions[] = ['id' => $type->value, 'status' => $type->name];
        }

        DB::table('transfer_status')->insert($transferStatusOptions);

        $transferTypes = [
            ['id' => 1, 'type' => 'FREE_TRANSFER'],
            ['id' => 2, 'type' => 'LOAN_TRANSFER'],
            ['id' => 3, 'type' => 'PERMANENT_TRANSFER'],
        ];

        DB::table('transfer_types')->insert($transferTypes);
    }

    public function down()
    {
        $ids = array_map(function ($type) {
            return $type->value;
        }, TransferStatusTypes::TRANSFER_STATUS_TYPES_LIST);
        DB::table('transfer_status')->whereIn('id', $ids)->delete();
        DB::table('transfer_status')->truncate();
    }
}
