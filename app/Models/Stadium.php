<?php

namespace App\Models;

use App\Services\StadiumService\StadiumType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stadium extends Model
{
    use HasFactory;

    protected $table = 'stadiums';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['type' => StadiumType::class, 'commercial_limit' => 'integer'];
    }

    public function commercialProducts(): HasMany
    {
        return $this->hasMany(StadiumCommercialProduct::class);
    }

    public function stands(): HasMany
    {
        return $this->hasMany(StadiumStand::class);
    }
}
