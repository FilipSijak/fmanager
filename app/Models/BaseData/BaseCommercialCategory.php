<?php

namespace App\Models\BaseData;

use App\Services\StadiumService\StadiumType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;

class BaseCommercialCategory extends Model
{
    protected $table = 'base_commercial_categories';

    public $timestamps = false;

    protected $fillable = ['slug', 'name', 'description', 'is_active'];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailableForStadiumType(Builder $query, StadiumType $type): Builder
    {
        return $query->whereExists(function (QueryBuilder $query) use ($type): void {
            $query->selectRaw('1')
                ->from('base_commercial_category_stadium_type')
                ->whereColumn('base_commercial_category_stadium_type.category_id', 'base_commercial_categories.id')
                ->where('base_commercial_category_stadium_type.stadium_type', $type->value);
        });
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(BaseCommercialProducts::class, 'category_id');
    }

    public function venueCosts(): HasMany
    {
        return $this->hasMany(BaseCommercialVenueCost::class, 'category_id');
    }
}
