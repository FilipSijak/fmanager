<?php

namespace App\Http\Controllers\Api;

use App\Domain\PlayerPerformance;
use App\Helpers\ResponseHelper;
use App\Http\Requests\BrowseSeasonRequest;
use App\Http\Resources\PlayerResource;
use App\Repositories\PlayerRepository;
use App\Support\GameContext;
use Illuminate\Http\JsonResponse;

class PlayerController extends CoreController
{
    public function __construct(
        private readonly GameContext $gameContext,
        private readonly PlayerRepository $players,
        private readonly PlayerPerformance $performance
    ) {}

    public function show(BrowseSeasonRequest $request, int $playerId): JsonResponse
    {
        $instanceId = $this->gameContext->instanceId();

        $player = $this->players->profile($instanceId, $playerId);

        $date = $this->gameContext->instanceDate();
        $seasons = $this->performance->seasons($player, $date);
        $currentSeasonId = $this->gameContext->hasSeasonId() ? $this->gameContext->seasonId() : null;
        $seasonId = $request->filled('season_id') ? $request->integer('season_id') : $currentSeasonId;
        abort_if($request->filled('season_id') && ! collect($seasons)->contains('id', $seasonId), 404);

        return ResponseHelper::success(
            (new PlayerResource($player))->toArray($request) + [
                'fitness' => $this->performance->fitness($player, $date),
                'performance' => $this->performance->summary($player, $seasonId, $date) + [
                    'seasons' => $seasons, 'current_season_id' => $currentSeasonId,
                ],
            ],
            ResponseHelper::RESPONSE_SUCCESS_CODE
        );
    }
}
