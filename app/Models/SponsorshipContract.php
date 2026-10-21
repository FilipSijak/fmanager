<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGameInstance;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SponsorshipContract extends Model
{
    use BelongsToGameInstance, HasFactory;

    protected $fillable = ['instance_id', 'club_id', 'sponsor_account_id', 'annual_amount', 'starts_at', 'expires_at'];

    protected function casts(): array
    {
        return [
            'annual_amount' => 'integer',
            'starts_at' => 'immutable_date',
            'expires_at' => 'immutable_date',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function sponsorAccount(): BelongsTo
    {
        return $this->belongsTo(GameEntityAccount::class, 'sponsor_account_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SponsorshipPayment::class);
    }
}
