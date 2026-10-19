<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGameInstance;
use App\Services\ManagerJobService\ManagerJobApplicationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManagerJobApplication extends Model
{
    use BelongsToGameInstance, HasFactory;

    public $timestamps = false;

    protected $casts = [
        'applied_on' => 'date',
        'status' => ManagerJobApplicationStatus::class,
    ];

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function staffCoaching(): BelongsTo
    {
        return $this->belongsTo(StaffCoaching::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ManagerJobApplicationStatus::Pending);
    }
}
