<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGameInstance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffCoaching extends Model
{
    use BelongsToGameInstance, HasFactory;

    protected $table = 'staff_coaching';

    public $timestamps = false;

    protected $casts = [
        'is_retired' => 'boolean',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_retired', false);
    }

    public function scopeRetired(Builder $query): Builder
    {
        return $query->where('is_retired', true);
    }
}
