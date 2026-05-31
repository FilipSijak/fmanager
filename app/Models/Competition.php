<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGameInstance;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Competition extends Model
{
    use HasFactory, BelongsToGameInstance;

    public $timestamps = false;

    public function clubs(): BelongsToMany
    {
        return $this->belongsToMany(Club::class, 'competition_season');
    }

    public function seasons(): BelongsToMany
    {
        return $this->belongsToMany(Season::class, 'competition_season');
    }
}
