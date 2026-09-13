<?php

namespace App\Models\BaseData;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BaseCommercialProducts extends Model
{
    protected $table = 'base_commercial_products';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['base_price' => 'integer'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BaseCommercialCategory::class, 'category_id');
    }
}
