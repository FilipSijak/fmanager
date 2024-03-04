<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\CompetitionResource;
use App\Models\Competition;
use App\Repositories\CompetitionRepository;
use Illuminate\Http\Request;

class CompetitionController extends CoreController
{
    private CompetitionRepository $competitionRepository;

    public function __construct(
        Request $request,
        CompetitionRepository $competitionRepository
    )
    {
        parent::__construct($request);

        $this->competitionRepository = $competitionRepository;

        $this->competitionRepository->setInstanceId($this->instanceId);
        $this->competitionRepository->setSeasonId($this->seasonId);
    }

    public function show(int $competitionId)
    {
        $competition = Competition::where('instance_id', $this->instanceId)
                                  ->where('id', $competitionId)
                                  ->first();

        return new CompetitionResource($competition);
    }

    public function competitionTable(int $competitionId)
    {
        $competitionTable = $this->competitionRepository->competitionTable($competitionId);

        return ResponseHelper::success((array)$competitionTable, ResponseHelper::RESPONSE_SUCCESS_CODE);
    }

    public function tournamentGroupsTables(int $competitionId)
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

    public function competitionKnockoutPhase(int $competitionId)
    {
        $knockoutSummary = $this->competitionKnockoutPhase($competitionId);
    }
}
