<?php

namespace App\Models;

use App\Models\BaseData\BaseFormation;
use App\Services\TacticsService\Mentality;
use App\Services\TacticsService\PassingStyle;
use App\Services\TacticsService\PressingIntensity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClubTactic extends Model
{
    use HasFactory;

    protected $fillable = ['club_id', 'base_formation_id', 'name', 'mentality', 'pressing', 'passing'];

    protected function casts(): array
    {
        return ['mentality' => Mentality::class, 'pressing' => PressingIntensity::class, 'passing' => PassingStyle::class];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function formation(): BelongsTo
    {
        return $this->belongsTo(BaseFormation::class, 'base_formation_id');
    }
}
