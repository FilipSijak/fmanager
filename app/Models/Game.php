<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGameInstance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    use HasFactory, BelongsToGameInstance;

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

    public function scopeForClub(Builder $query, int $clubId):Builder
    {
        return $query->where(function ($query) use ($clubId): void {
            $query->where('hometeam_id', $clubId)
                ->orWhere('awayteam_id', $clubId);
        });
    }
}
