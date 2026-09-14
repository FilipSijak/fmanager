<?php

namespace App\Models\BaseData;

use App\Services\StadiumService\StadiumType;
use App\StadiumStandPosition;
use Illuminate\Database\Eloquent\Model;

class BaseStadiumStandCapacityLimit extends Model
{
    protected $table = 'base_stadium_stand_capacity_limits';

    public $timestamps = false;

    protected $fillable = ['stadium_type', 'position', 'maximum_capacity'];

    protected function casts(): array
    {
        return [
            'stadium_type' => StadiumType::class,
            'position' => StadiumStandPosition::class,
            'maximum_capacity' => 'integer',
        ];
    }
}
