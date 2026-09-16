<?php

namespace App\Models;

use App\Models\BaseData\BaseCommercialCategory;
use App\Services\CommercialService\CommercialVenueSize;
use Database\Factories\StadiumCommercialVenueFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StadiumCommercialVenue extends Model
{
    /** @use HasFactory<StadiumCommercialVenueFactory> */
    use HasFactory;

    protected $fillable = ['instance_id', 'stadium_id', 'category_id', 'size', 'build_cost'];

    public $timestamps = false;

    public function scopeForStadium(Builder $query, Stadium $stadium): Builder
    {
        return $query
            ->whereBelongsTo($stadium)
            ->where('instance_id', $stadium->instance_id);
    }

    protected function casts(): array
    {
        return ['size' => CommercialVenueSize::class, 'build_cost' => 'integer'];
    }

    public function mortgageLoan(): HasOne
    {
        return $this->hasOne(FinanceEntityLoan::class, 'stadium_commercial_venue_id');
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(Instance::class);
    }

    public function stadium(): BelongsTo
    {
        return $this->belongsTo(Stadium::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BaseCommercialCategory::class, 'category_id');
    }
}
