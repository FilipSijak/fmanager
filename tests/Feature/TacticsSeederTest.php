<?php

namespace Tests\Feature;

use App\Models\BaseData\BaseFormation;
use App\Models\BaseData\BaseFormationSlot;
use App\Services\TacticsService\FormationTendency;
use Database\Seeders\TacticsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TacticsSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function seeds_a_balanced_4_2_3_1(): void
    {
        $this->seed(TacticsSeeder::class);

        $formation = BaseFormation::query()->where('code', '4-2-3-1')->with('slots')->firstOrFail();

        $this->assertTrue($formation->is_active);
        $this->assertSame(FormationTendency::BALANCED, $formation->tactical_tendency);
        $this->assertSame(
            ['GK', 'LB', 'CB', 'CB', 'RB', 'DM', 'DM', 'LW', 'AM', 'RW', 'ST'],
            $formation->slots->pluck('position')->all(),
        );
    }

    #[Test]
    public function every_seeded_formation_has_eleven_slots_with_one_goalkeeper(): void
    {
        $this->seed(TacticsSeeder::class);

        foreach (BaseFormation::query()->with('slots')->get() as $formation) {
            $this->assertCount(11, $formation->slots, $formation->code);
            $this->assertSame(1, $formation->slots->filter(fn (BaseFormationSlot $slot): bool => $slot->position === 'GK')->count(), $formation->code);
        }
    }
}
