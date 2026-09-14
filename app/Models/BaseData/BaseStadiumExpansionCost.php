<?php

namespace App\Models\BaseData;

use Illuminate\Database\Eloquent\Model;

class BaseStadiumExpansionCost extends Model
{
    protected $table = 'base_stadium_expansion_costs';

    public $timestamps = false;

    protected $fillable = ['stadium_type', 'cost_per_1000_seats'];

    protected function casts(): array
    {
        return ['cost_per_1000_seats' => 'integer'];
    }
}
