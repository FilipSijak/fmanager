<?php

namespace App\Models;

use App\StadiumStandPosition;
use App\StadiumStandStatus;
use Database\Factories\StadiumStandFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StadiumStand extends Model
{
    /** @use HasFactory<StadiumStandFactory> */
    use HasFactory;

    protected $fillable = ['stadium_id', 'position', 'capacity', 'status'];

    public function scopeWithConstruction(Builder $query): Builder
    {
        return $query->with('construction');
    }

    protected function casts(): array
    {
        return [
            'position' => StadiumStandPosition::class,
            'status' => StadiumStandStatus::class,
            'capacity' => 'integer',
        ];
    }

    public function stadium(): BelongsTo
    {
        return $this->belongsTo(Stadium::class);
    }

    public function construction(): HasOne
    {
        return $this->hasOne(StadiumStandConstruction::class);
    }
}
