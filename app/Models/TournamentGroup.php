<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TournamentGroup extends Model
{
    public $table = 'tournament_groups';
    public $timestamps = false;

    use HasFactory;
}
