<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerInjury extends Model
{
    use HasFactory;

    protected $table = 'player_injuries';

    public $timestamps = false;

    public function scopeForPlayer(Builder $query, Player $player): Builder
    {
        return $query->where('player_injuries.player_id', $player->id)
            ->where('player_injuries.instance_id', $player->instance_id);
    }

    public function scopeActiveOn(Builder $query, string $date): Builder
    {
        return $query->where('player_injuries.injury_start_date', '<=', $date)
            ->where('player_injuries.injury_end_date', '>=', $date);
    }
}
