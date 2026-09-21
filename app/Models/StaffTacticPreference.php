<?php

namespace App\Models;

use App\Models\BaseData\BaseFormation;
use App\Services\TacticsService\Mentality;
use App\Services\TacticsService\PassingStyle;
use App\Services\TacticsService\PressingIntensity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffTacticPreference extends Model
{
    use HasFactory;

    protected $fillable = ['staff_coaching_id', 'base_formation_id', 'mentality', 'pressing', 'passing', 'is_customized'];

    protected function casts(): array
    {
        return ['mentality' => Mentality::class, 'pressing' => PressingIntensity::class, 'passing' => PassingStyle::class, 'is_customized' => 'boolean'];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(StaffCoaching::class, 'staff_coaching_id');
    }

    public function formation(): BelongsTo
    {
        return $this->belongsTo(BaseFormation::class, 'base_formation_id');
    }
}
