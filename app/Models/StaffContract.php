<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffContract extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'contract_start' => 'date:Y-m-d',
        'contract_end' => 'date:Y-m-d',
        'salary' => 'integer',
        'signing_fee' => 'integer',
    ];
}
