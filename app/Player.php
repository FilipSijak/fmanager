<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Position;

class Player extends Model
{
    public function positions()
    {
        return $this->belongsToMany(Position::class, 'player_positions')->withPivot('player_id', 'position_id', 'position_grade');
    }
}
