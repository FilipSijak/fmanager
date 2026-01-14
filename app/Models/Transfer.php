<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transfer extends Model
{
    use HasFactory;

    public $timestamps = false;

    public function transferFinancialDetails()
    {
        return $this->hasOne(TransferFinancialDetails::class);
    }

    public function scopeCanClubAffordTransfer(Builder $builder, Club $club)
    {
        $transferFinancialDetails = $this->transferFinancialDetails()->first();

        return $club->account()->first()->transfer_budget >= $transferFinancialDetails->amount;
    }
}
