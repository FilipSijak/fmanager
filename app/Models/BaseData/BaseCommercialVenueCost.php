<?php

namespace App\Models\BaseData;

use App\Services\CommercialService\CommercialVenueSize;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BaseCommercialVenueCost extends Model
{
    protected $table = 'base_commercial_venue_costs';

    public $timestamps = false;

    protected $fillable = ['category_id', 'size', 'base_cost'];

    protected function casts(): array
    {
        return [
            'size' => CommercialVenueSize::class,
            'base_cost' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BaseCommercialCategory::class, 'category_id');
    }
}
