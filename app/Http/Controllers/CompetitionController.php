<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\CompetitionResource;
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
        $competitionTable = $this->competitionRepository->competitionTable($competitionId);

        return ResponseHelper::success($competitionTable->toArray(), ResponseHelper::RESPONSE_SUCCESS_CODE);
    }

    public function tournamentGroupsTables(int $competitionId): JsonResponse
    {
        $tournamentGroups = $this->competitionRepository->tournamentGroupsTables($competitionId);
        $tournamentsGroupsMapping = [];

        foreach ($tournamentGroups as $groupItem) {
            if (!isset($tournamentsGroupsMapping[$groupItem->group_id])) {
                $tournamentsGroupsMapping[$groupItem->group_id] = [];
            }

            $tournamentsGroupsMapping[$groupItem->group_id][] = $groupItem;
        }

        return ResponseHelper::success($tournamentsGroupsMapping, ResponseHelper::RESPONSE_SUCCESS_CODE);
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
}
