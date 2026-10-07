<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The Squad page used to save starters under a fixed 4-4-2 chip list, which the
     * Tactics page placed into the formation slot at the same index. Mapping chip
     * index to formation slot id keeps each starter in that intended slot.
     */
    private const LEGACY_CHIP_TO_FORMATION_SLOT = [
        'GK' => '1',
        'DL' => '2',
        'DR' => '3',
        'DC-1' => '4',
        'DC-2' => '5',
        'ML' => '6',
        'MR' => '7',
        'MC-1' => '8',
        'MC-2' => '9',
        'FC-1' => '10',
        'FC-2' => '11',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::LEGACY_CHIP_TO_FORMATION_SLOT as $legacyChip => $formationSlot) {
            DB::table('club_tactic_players_slots')->where('slot', $legacyChip)->update(['slot' => $formationSlot]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::LEGACY_CHIP_TO_FORMATION_SLOT as $legacyChip => $formationSlot) {
            DB::table('club_tactic_players_slots')->where('slot', $formationSlot)->update(['slot' => $legacyChip]);
        }
    }
};
