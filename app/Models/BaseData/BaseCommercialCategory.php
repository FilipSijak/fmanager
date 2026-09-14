<?php

namespace App\Models\BaseData;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BaseCommercialCategory extends Model
{
    protected $table = 'base_commercial_categories';

    public $timestamps = false;

    protected $fillable = ['slug', 'name', 'description', 'is_active'];

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
