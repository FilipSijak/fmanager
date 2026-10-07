<?php

namespace App\Models;

use App\Models\BaseData\BaseFormation;
use App\Services\TacticsService\Mentality;
use App\Services\TacticsService\PassingStyle;
use App\Services\TacticsService\PressingIntensity;
use App\Services\TacticsService\TacklingStyle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffTacticPreference extends Model
{
    use HasFactory;

    /** Mirrors the column defaults so freshly created models carry them before being re-fetched. */
    protected $attributes = [
        'tackling' => 'normal',
        'offside_trap' => false,
        'counter_attack' => false,
        'men_behind_ball' => false,
    ];

    protected $fillable = ['staff_coaching_id', 'base_formation_id', 'mentality', 'pressing', 'passing', 'tackling', 'offside_trap', 'counter_attack', 'men_behind_ball', 'is_customized'];

    protected function casts(): array
    {
        return [
            'mentality' => Mentality::class,
            'pressing' => PressingIntensity::class,
            'passing' => PassingStyle::class,
            'tackling' => TacklingStyle::class,
            'offside_trap' => 'boolean',
            'counter_attack' => 'boolean',
            'men_behind_ball' => 'boolean',
            'is_customized' => 'boolean',
        ];
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
