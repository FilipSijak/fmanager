<?php

namespace App\Models\BaseData;

use App\Services\TacticsService\FormationTendency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BaseFormation extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['code', 'name', 'description', 'is_active', 'tactical_tendency'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'tactical_tendency' => FormationTendency::class];
    }

    /** Slots in their seeded order (GK first); `slot` is a string, so it can't be sorted on directly. */
    public function slots(): HasMany
    {
        return $this->hasMany(BaseFormationSlot::class)->orderBy('id');
    }
}
