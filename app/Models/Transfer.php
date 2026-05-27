<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function sourceClub(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'source_club_id');
    }

    public function targetClub(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'target_club_id');
    }

    public function transferFinancialDetails()
    {
        return $this->hasOne(TransferFinancialDetails::class);
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
