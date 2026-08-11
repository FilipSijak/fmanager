<?php

namespace App\Services\SeasonService;

use App\Models\Instance;
use App\Repositories\CompetitionRepository;
use App\Services\CompetitionService\Progression\SeasonProgressionService;
use Illuminate\Support\Facades\DB;

class SeasonCompletion
{
    public function __construct(
        private readonly SeasonProgressionService $seasonProgressionService,
        private readonly CompetitionRepository $competitionRepository
    ) {}

    public function process(Instance $instance): void
    {
        DB::transaction(function () use ($instance): void {
            $sourceSeasonId = (int) $instance->season_id;

            $this->seasonProgressionService->finalize($sourceSeasonId);
            $nextSeason = $this->competitionRepository->applyCompetitionProgressions(
                (int) $instance->id,
                $sourceSeasonId
            );

            $instance->season_id = $nextSeason->id;
            $instance->save();
        });
    }
}
