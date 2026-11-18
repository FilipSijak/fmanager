<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGameInstance;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Game extends Model
{
    use BelongsToGameInstance, HasFactory;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_POSTPONED = 'postponed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_ABANDONED = 'abandoned';

    public $timestamps = false;

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    public function scopeForClub(Builder $query, int $clubId): Builder
    {
        return $query->where(function ($query) use ($clubId): void {
            $query->where('hometeam_id', $clubId)
                ->orWhere('awayteam_id', $clubId);
        });
    }

    public function scopeNotCancelled(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_CANCELLED);
    }

    public function scopeStartingBetween(Builder $query, CarbonInterface $from, CarbonInterface $until): Builder
    {
        return $query->where('match_start', '>=', $from)->where('match_start', '<', $until);
    }

    public function playerStats(): HasMany
    {
        return $this->hasMany(GamePlayerStat::class);
    }

    public function matchdaySettlement(): HasOne
    {
        return $this->hasOne(MatchdaySettlement::class);
    }
}
