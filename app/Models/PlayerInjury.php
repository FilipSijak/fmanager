<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerInjury extends Model
{
    use HasFactory;

    protected $table = 'player_injuries';
    public $timestamps = false;
}
