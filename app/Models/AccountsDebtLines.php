<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountsDebtLines extends Model
{
    public $table = 'accounts_debt_lines';
    public $timestamps = false;

    protected $fillable = [
        'sending_account_id',
        'receiving_account_id',
        'created_at',
        'due_date',
        'amount',
    ];
}
