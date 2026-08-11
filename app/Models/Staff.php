<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGameInstance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    use BelongsToGameInstance, HasFactory;

    protected $table = 'staff';

    public $timestamps = false;

    protected $casts = [
        'is_retired' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_retired', false);
    }

    public function scopeRetired(Builder $query): Builder
    {
        return $query->where('is_retired', true);
    }
}
