<?php

namespace App\Models;

use App\Services\StadiumService\StadiumType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public function instance(): BelongsTo
    {
        return $this->belongsTo(Instance::class);
    }

    public function commercialProducts(): HasMany
    {
        return $this->hasMany(StadiumCommercialProduct::class);
    }

    public function stands(): HasMany
    {
        return $this->hasMany(StadiumStand::class);
    }

    public function standConstructions(): HasMany
    {
        return $this->hasMany(StadiumStandConstruction::class);
    }

    public function commercialVenues(): HasMany
    {
        return $this->hasMany(StadiumCommercialVenue::class);
    }
}
