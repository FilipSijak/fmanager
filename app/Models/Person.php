<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGameInstance;
use App\Services\PersonService\GeneratePeople\PersonInfo;
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

    public function getPersonDetailsAttribute(): PersonInfo
    {
        return new PersonInfo(
            firstName: $this->first_name,
            lastName: $this->last_name,
            countryCode: $this->country_code,
            dateOfBirth: $this->dob->toDateString(),
        );
    }

    public function player(): HasOne
    {
        return $this->hasOne(Player::class);
    }

    public function coachingCareers(): HasMany
    {
        return $this->hasMany(StaffCoaching::class);
    }

    public function physioCareers(): HasMany
    {
        return $this->hasMany(StaffPhysio::class);
    }

    public function scoutCareers(): HasMany
    {
        return $this->hasMany(StaffScout::class);
    }
}
