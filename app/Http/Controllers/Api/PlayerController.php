<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Resources\PlayerResource;
use App\Models\Player;
use App\Repositories\PlayerPerformanceRepository;
use App\Support\GameContext;
use Illuminate\Http\JsonResponse;

class PlayerController extends CoreController
{
    public function __construct(
        private readonly GameContext $gameContext,
        private readonly PlayerPerformanceRepository $performance
    ) {}

    public function show(int $playerId): JsonResponse
    {
        $instanceId = $this->gameContext->instanceId();

        $player = Player::query()
            ->with(['person', 'club', 'contract'])
            ->forInstance($instanceId)
            ->findOrFail($playerId);

        return ResponseHelper::success(
            (new PlayerResource($player))->toArray(request()) + [
                'performance' => $this->performance->summary($player, $this->gameContext->seasonId(), $this->gameContext->instanceDate()),
            ],
            ResponseHelper::RESPONSE_SUCCESS_CODE
        );
    }
}
