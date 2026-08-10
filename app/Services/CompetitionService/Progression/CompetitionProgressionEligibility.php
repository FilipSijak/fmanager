<?php

namespace App\Services\CompetitionService\Progression;

use App\Models\Game;
use Illuminate\Support\Facades\DB;
use LogicException;

class CompetitionProgressionEligibility
{
    public function assertCompetitionFinished(int $instanceId, int $seasonId, int $competitionId): void
    {
        $blocking = DB::table('games')
            ->where('instance_id', $instanceId)
            ->where('season_id', $seasonId)
            ->where('competition_id', $competitionId)
            ->whereIn('status', [Game::STATUS_SCHEDULED, Game::STATUS_IN_PROGRESS, Game::STATUS_POSTPONED])
            ->exists();

        if ($blocking) {
            throw new LogicException("Competition {$competitionId} still has unresolved games.");
        }
    }
}
