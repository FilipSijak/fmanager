<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGameInstance;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Season extends Model
{
    use BelongsToGameInstance, HasFactory;

    public $timestamps = false;

    public function clubs()
    {
        $this->belongsToMany(Club::class, 'competition_season');
    }

    public function competitions()
    {
        $this->belongsToMany(Competition::class, 'competition_season');
    }
}
