<?php

namespace Database\Factories;

use App\Models\BaseData\BaseFormation;
use App\Models\StaffCoaching;
use App\Models\StaffTacticPreference;
use App\Services\TacticsService\Mentality;
use App\Services\TacticsService\PassingStyle;
use App\Services\TacticsService\PressingIntensity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffTacticPreference>
 */
class StaffTacticPreferenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'staff_coaching_id' => StaffCoaching::factory(),
            'base_formation_id' => BaseFormation::factory(),
            'mentality' => Mentality::BALANCED,
            'pressing' => PressingIntensity::MEDIUM,
            'passing' => PassingStyle::MIXED,
        ];
    }
}
