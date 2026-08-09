<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\CompetitionResource;
use App\Http\Resources\CompetitionTableRowResource;
use App\Models\Competition;
use App\Repositories\CompetitionRepository;
use App\Repositories\GameRepository;
use App\Services\CompetitionService\Competitions\KnockoutSummaryRoundsData;
use App\Support\GameContext;
use Illuminate\Http\JsonResponse;

class CompetitionController extends CoreController
{
    public function __construct(
        private readonly GameContext $gameContext,
        private readonly  CompetitionRepository $competitionRepository,
        private readonly GameRepository $gameRepository,
        private readonly KnockoutSummaryRoundsData $knockoutSummaryRoundsData
    ) {
    }

    public function show(int $competitionId): JsonResponse
    {
        $instanceId = $this->gameContext->instanceId();

        $competition = Competition::query()
            ->forInstance($instanceId)
            ->findOrFail($competitionId);

        return ResponseHelper::success(
            (new CompetitionResource($competition))->toArray(request()),
            ResponseHelper::RESPONSE_SUCCESS_CODE
        );
    }

    public function competitionTable(int $competitionId): JsonResponse
    {
        $competitionTable = $this->competitionRepository->competitionTable($competitionId)
            ->values()
            ->each(function ($row, int $index): void {
                $row->position = $index + 1;
            });

        return ResponseHelper::success(
            (CompetitionTableRowResource::collection($competitionTable))->toArray(request()),
            ResponseHelper::RESPONSE_SUCCESS_CODE
        );
    }

    public function tournamentGroupsTables(int $competitionId): JsonResponse
    {
        $tournamentGroupsTables = $this->competitionRepository->tournamentGroupsTables($competitionId)
            ->groupBy('group_id')
            ->map(function ($groupTable) {
                $groupTable = $groupTable->values()
                    ->each(function ($row, int $index): void {
                        $row->position = $index + 1;
                    });

                return CompetitionTableRowResource::collection($groupTable)->toArray(request());
            })
            ->toArray();

        return ResponseHelper::success($tournamentGroupsTables, ResponseHelper::RESPONSE_SUCCESS_CODE);
    }

    public function competitionKnockoutPhaseRoundViewData(int $competitionId): JsonResponse
    {
        $summary = $this->competitionRepository->getCompetitionKnockoutStageSummary($competitionId);

        if ($summary) {
            return ResponseHelper::success(
                $this->knockoutSummaryRoundsData->displayCurrentRound($summary),
                ResponseHelper::RESPONSE_SUCCESS_CODE
            );
        }

        return ResponseHelper::error(
            'Unable to load knockout summary data',
            '',
            ResponseHelper::RESPONSE_ERROR_CODE
        );
    }

    public function competitionKnockoutPhaseAllRounds($competitionId): JsonResponse
    {
        $summary = $this->competitionRepository->getCompetitionKnockoutStageSummary($competitionId);

        if ($summary) {
            return ResponseHelper::success(
                $this->knockoutSummaryRoundsData->displayAllRounds($summary),
                ResponseHelper::RESPONSE_SUCCESS_CODE
            );
        }

        return ResponseHelper::error(
            'Unable to load knockout summary data',
            '',
            ResponseHelper::RESPONSE_ERROR_CODE
        );
    }

    public function competitionKnockoutPhase(int $competitionId): JsonResponse
    {
        $summary = $this->competitionRepository->getCompetitionKnockoutStageSummary($competitionId);

        if ($summary) {
            return ResponseHelper::success(
                json_decode($summary, true) ?? [],
                ResponseHelper::RESPONSE_SUCCESS_CODE
            );
        }

        return ResponseHelper::error(
            'Unable to load knockout summary data',
            '',
            ResponseHelper::RESPONSE_ERROR_CODE
        );
    }
}
