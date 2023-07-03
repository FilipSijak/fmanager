<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Competition extends Model
{
    use HasFactory;

    public $timestamps = false;

    public function clubs()
    {
        return $this->belongsToMany(Club::class, 'competition_season');
    }

    public function seasons()
    {
        return $this->belongsToMany(Season::class, 'competition_season');
    }
}
