<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransferFinancialDetails extends Model
{
    use HasFactory;

    protected $fillable = [
        'amount',
        'transfer_id',
        'installments',
    ];

    public $table = 'transfer_financial_details';
    public $timestamps = false;
}
