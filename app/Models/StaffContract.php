<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    public function scopeActiveOn(Builder $query, string $date): Builder
    {
        return $query->whereDate('contract_start', '<=', $date)
            ->whereDate('contract_end', '>=', $date);
    }
}
