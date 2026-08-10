<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompetitionProgressionRulesSeeder extends Seeder
{
    public function run(): void
    {
        $this->rule(1, 8, 'relegation', 'bottom_positions', null, null, 3, null, 10);
        $this->rule(8, 1, 'promotion', 'position_range', 1, 3, null, null, 10);

        foreach ([1, 2, 3, 4, 5] as $leagueId) {
            $this->rule($leagueId, 6, 'continental', 'position_range', 1, 4, null, 'group_stage', 10);
            $this->rule($leagueId, 7, 'continental', 'position_range', 5, 5, null, 'group_stage', 20);
        }
    }

    private function rule(
        int $source,
        int $target,
        string $progressionType,
        string $selectorType,
        ?int $positionFrom,
        ?int $positionTo,
        ?int $places,
        ?string $entryStage,
        int $priority
    ): void {
        DB::table('competition_progression_rules')->updateOrInsert(
            [
                'source_base_competition_id' => $source,
                'target_base_competition_id' => $target,
                'progression_type' => $progressionType,
                'selector_type' => $selectorType,
            ],
            [
                'position_from' => $positionFrom,
                'position_to' => $positionTo,
                'places' => $places,
                'entry_stage' => $entryStage,
                'duplicate_policy' => 'next_league_position',
                'priority' => $priority,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
