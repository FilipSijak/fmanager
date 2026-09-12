<?php

namespace App\Models;

use App\Observers\StadiumStandObserver;
use App\StadiumStandPosition;
use App\StadiumStandStatus;
use Database\Factories\StadiumStandFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([StadiumStandObserver::class])]
class StadiumStand extends Model
{
    /** @use HasFactory<StadiumStandFactory> */
    use HasFactory;

    protected $fillable = ['stadium_id', 'position', 'capacity', 'status'];

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
}
