<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Club extends Model
{
    use HasFactory;

    public $timestamps = false;

    public function competitions()
    {
        $this->belongsToMany(Competition::class, 'competition_season');
    }

    public function seasons()
    {
        $this->belongsToMany(Season::class, 'competition_season');
    }
}
