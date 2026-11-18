<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGameInstance;
use Database\Factories\ClubDemandProfileFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClubDemandProfile extends Model
{
    /** @use HasFactory<ClubDemandProfileFactory> */
    use BelongsToGameInstance, HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'period_start' => 'immutable_date',
            'club_rank' => 'integer',
            'country_rank' => 'integer',
            'performance_score' => 'float',
            'ticket_price_sensitivity' => 'float',
            'commercial_price_sensitivity' => 'float',
        ];
    }

    public function scopeForClub(Builder $query, Club $club): Builder
    {
        return $query->forInstance($club->instance_id)->whereBelongsTo($club);
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }
}
