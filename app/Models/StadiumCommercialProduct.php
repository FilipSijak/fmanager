<?php

namespace App\Models;

use App\Models\BaseData\BaseCommercialProducts;
use Database\Factories\StadiumCommercialProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StadiumCommercialProduct extends Model
{
    /** @use HasFactory<StadiumCommercialProductFactory> */
    use HasFactory;

    protected $table = 'stadium_commercial_products';

    public $timestamps = false;

    protected $fillable = ['instance_id', 'stadium_id', 'base_product_id', 'base_price', 'price_change_coef', 'is_available'];

    protected function casts(): array
    {
        return ['base_price' => 'integer', 'price_change_coef' => 'decimal:4', 'is_available' => 'boolean'];
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(Instance::class);
    }

    public function stadium(): BelongsTo
    {
        return $this->belongsTo(Stadium::class);
    }

    public function baseProduct(): BelongsTo
    {
        return $this->belongsTo(BaseCommercialProducts::class, 'base_product_id');
    }
}
