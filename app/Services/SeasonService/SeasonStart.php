<?php

namespace App\Services\SeasonService;

use App\Models\Club;
use App\Models\Competition;
use App\Models\Instance;
use App\Models\Season;
use App\Repositories\CompetitionRepository;
use App\Services\CompetitionService\CompetitionService;
use Illuminate\Support\Facades\DB;

class SeasonStart
{
    public function __construct(
        private readonly CompetitionRepository $competitionRepository,
        private readonly CompetitionService $competitionService,
        private readonly PlayerRetirement $playerRetirement
    ) {}

    public function process(Instance $instance): void
    {
        $season = Season::query()
            ->where('instance_id', $instance->id)
            ->findOrFail($instance->season_id);

        $this->playerRetirement->retireEligiblePlayers($instance);

        Competition::query()
            ->forInstance($instance->id)
            ->orderBy('id')
            ->each(function (Competition $competition) use ($instance, $season): void {
                if ($this->alreadyScheduled($instance->id, $season->id, $competition->id)) {
                    return;
                }

                $clubIds = $this->competitionRepository->clubIdsForCompetitionSeason(
                    $competition->id,
                    $season->id,
                    $instance->id
                );

                if ($clubIds === []) {
                    return;
                }

                if ($competition->type === 'league') {
                    $this->competitionService->makeLeague(
                        $clubIds,
                        $competition->id,
                        $season->id,
                        $instance->id
                    );

                    return;
                }

                if ($competition->type !== 'tournament') {
                    return;
                }

                $clubs = Club::query()
                    ->forInstance($instance->id)
                    ->whereIn('id', $clubIds)
                    ->get()
                    ->sortBy(fn (Club $club): int => array_search($club->id, $clubIds, true))
                    ->values();

                if ((int) $competition->groups === 1) {
                    $this->competitionService->makeTournamentGroupStage(
                        $clubs,
                        $competition->id,
                        $season->id,
                        $instance->id
                    );

                    return;
                }

                $this->competitionService->makeTournament(
                    $clubs,
                    $competition->id,
                    $season->id,
                    $instance->id
                );
            });
    }

    private function alreadyScheduled(int $instanceId, int $seasonId, int $competitionId): bool
    {
        return DB::table('games')
            ->where('instance_id', $instanceId)
            ->where('season_id', $seasonId)
            ->where('competition_id', $competitionId)
            ->exists();
    }
}
