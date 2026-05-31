<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\PlayerResource;
use App\Models\Player;
use App\Support\GameContext;

class PlayerController extends CoreController
{
    public function __construct(private readonly GameContext $gameContext)
    {
    }

    public function show(int $playerId)
    {
        $instanceId = $this->gameContext->instanceId();

        $player = Player::query()
            ->with(['club', 'contract'])
            ->forInstance($instanceId)
            ->findOrFail($playerId);

        return ResponseHelper::success(
            new PlayerResource($player)->toArray(request()),
            ResponseHelper::RESPONSE_SUCCESS_CODE
        );
    }
}
