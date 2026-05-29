<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    use HasFactory;

    public $timestamps = false;

    public function scopeForClub(Builder $query, int $clubId):Builder
    {
        return $query->where(function ($query) use ($clubId): void {
            $query->where('hometeam_id', $clubId)
                ->orWhere('awayteam_id', $clubId);
        });
    }
}
