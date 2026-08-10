<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompetitionProgressionRulesSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('league_tier_rules')->updateOrInsert(
            ['upper_base_competition_id' => 1, 'lower_base_competition_id' => 8],
            ['automatic_movement_places' => 3, 'active' => true, 'created_at' => now(), 'updated_at' => now()]
        );

        foreach ([1, 2, 3, 4, 5] as $leagueId) {
            $this->qualificationRule($leagueId, 6, 1, 4, 10);
            $this->qualificationRule($leagueId, 7, 5, 5, 20);
        }
    }

    private function qualificationRule(int $source, int $target, int $from, int $to, int $priority): void
    {
        DB::table('competition_qualification_rules')->updateOrInsert(
            [
                'source_base_competition_id' => $source,
                'target_base_competition_id' => $target,
                'selector_type' => 'league_position',
                'position_from' => $from,
                'position_to' => $to,
            ],
            [
                'entry_stage' => 'group_stage',
                'duplicate_policy' => 'next_league_position',
                'priority' => $priority,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
