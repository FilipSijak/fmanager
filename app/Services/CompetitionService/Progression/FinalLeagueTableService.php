<?php

namespace App\Services\CompetitionService\Progression;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinalLeagueTableService
{
    public function get(int $instanceId, int $seasonId, int $competitionId): Collection
    {
        return DB::table('competition_season')
            ->where('instance_id', $instanceId)
            ->where('season_id', $seasonId)
            ->where('competition_id', $competitionId)
            ->orderByDesc('points')
            ->orderByRaw('(goals_for - goals_against) DESC')
            ->orderByDesc('goals_for')
            ->orderBy('club_id')
            ->get()
            ->values()
            ->map(function ($row, int $index) {
                $row->position = $index + 1;
                return $row;
            });
    }
}
