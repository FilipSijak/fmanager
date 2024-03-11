<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\CompetitionResource;
use App\Models\Competition;
use App\Repositories\CompetitionRepository;
use App\Repositories\GameRepository;
use App\Services\CompetitionService\Competitions\KnockoutSummaryRoundsData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompetitionController extends CoreController
{
    private CompetitionRepository     $competitionRepository;
    private GameRepository            $gameRepository;
    private KnockoutSummaryRoundsData $knockoutSummaryRoundsData;

    public function __construct(
        Request $request,
        CompetitionRepository $competitionRepository,
        GameRepository $gameRepository
    )
    {
        parent::__construct($request);

        $this->competitionRepository = $competitionRepository;
        $this->competitionRepository->setInstanceId($this->instanceId);
        $this->competitionRepository->setSeasonId($this->seasonId);
        $this->gameRepository = $gameRepository;
        $this->gameRepository->setInstanceId($this->instanceId);
        $this->gameRepository->setSeasonId($this->seasonId);
        $this->knockoutSummaryRoundsData = new KnockoutSummaryRoundsData($this->gameRepository);
    }

    public function show(int $competitionId): CompetitionResource
    {
        $competition = Competition::where('instance_id', $this->instanceId)
                                  ->where('id', $competitionId)
                                  ->first();

        return new CompetitionResource($competition);
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
