<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToGameInstance
{
    public function scopeForInstance(Builder $query, int $instanceId): Builder
    {
        return $query->where('instance_id', $instanceId);
    }
}
