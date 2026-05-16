<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'instance_id',
        'season_id',
        'source_club_id',
        'player_id',
        'offer_date',
        'transfer_type',
    ];

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

    public function player()
    {
        return $this->belongsTo(Player::class);
    }

    public function season()
    {
        return $this->belongsTo(Season::class);
    }

    public function instance()
    {
        return $this->belongsTo(Instance::class);
    }

    public function transferContractOffer()
    {
        return $this->hasOne(TransferContractOffer::class);

    }

    public function transferFinancialDetail()
    {
        return $this->hasOne(TransferFinancialDetails::class);
    }
}
