<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGameInstance;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Person extends Model
{
    use BelongsToGameInstance, HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'dob' => 'date:Y-m-d',
    ];

    public function player(): HasOne
    {
        return $this->hasOne(Player::class);
    }

    public function staffCareers(): HasMany
    {
        return $this->hasMany(Staff::class);
    }
}
