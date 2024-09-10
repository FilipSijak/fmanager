<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransferContractOffer extends Model
{
    use HasFactory;

    public $table = 'transfer_contract_offers';
    public $timestamps = false;
}
