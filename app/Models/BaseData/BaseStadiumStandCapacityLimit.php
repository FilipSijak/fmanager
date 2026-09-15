<?php

namespace App\Models\BaseData;

use App\Services\StadiumService\StadiumType;
use App\StadiumStandPosition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BaseStadiumStandCapacityLimit extends Model
{
    protected $table = 'base_stadium_stand_capacity_limits';

    public $timestamps = false;

    protected $fillable = ['stadium_type', 'position', 'maximum_capacity'];

    public function scopeForType(Builder $query, StadiumType $type): Builder
    {
        return $query->where('stadium_type', $type->value);
    }

    public function scopeForPosition(Builder $query, StadiumStandPosition $position): Builder
    {
        return $query->where('position', $position->value);
    }

    protected function casts(): array
    {
        return [
            'stadium_type' => StadiumType::class,
            'position' => StadiumStandPosition::class,
            'maximum_capacity' => 'integer',
        ];
    }
}
