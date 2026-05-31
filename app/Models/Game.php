<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGameInstance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    use HasFactory, BelongsToGameInstance;

    public $timestamps = false;

    public function scopeForClub(Builder $query, int $clubId):Builder
    {
        return $query->where(function ($query) use ($clubId): void {
            $query->where('hometeam_id', $clubId)
                ->orWhere('awayteam_id', $clubId);
        });
    }
}
