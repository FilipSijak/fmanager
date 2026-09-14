<?php

namespace App\Models;

use Database\Factories\StadiumStandConstructionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StadiumStandConstruction extends Model
{
    /** @use HasFactory<StadiumStandConstructionFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['instance_id', 'stadium_id', 'stadium_stand_id', 'target_capacity', 'capacity_increase', 'started_at', 'completes_at'];

    protected function casts(): array
    {
        return ['target_capacity' => 'integer', 'capacity_increase' => 'integer', 'started_at' => 'immutable_date', 'completes_at' => 'immutable_date'];
    }

    public function stadium(): BelongsTo
    {
        return $this->belongsTo(Stadium::class);
    }

    public function stadiumStand(): BelongsTo
    {
        return $this->belongsTo(StadiumStand::class);
    }
}
