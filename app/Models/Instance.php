<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Instance extends Model
{
    use HasFactory;

    public $timestamps = false;

    public function stadiums(): HasMany
    {
        return $this->hasMany(Stadium::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function followedCompetition(): BelongsTo
    {
        return $this->belongsTo(Competition::class, 'followed_competition_id');
    }

    /**
     * The game world exists but the user has not taken over a club yet.
     */
    public function isAwaitingClubSelection(): bool
    {
        return $this->manager_id === null;
    }

    /**
     * The user has a manager in this game but no club, after resigning or being sacked.
     */
    public function isManagerUnemployed(): bool
    {
        return $this->manager_id !== null && $this->club_id === null;
    }
}
