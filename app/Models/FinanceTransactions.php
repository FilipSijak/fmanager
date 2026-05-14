<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceTransactions extends Model
{
    protected $fillable = [
        'sending_account_id',
        'receiving_account_id',
        'amount',
        'transaction_date',
    ];
    public $table = 'finance_transactions';
    public $timestamps = false;
}
