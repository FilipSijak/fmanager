<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\ClubResource;
use App\Http\Resources\ClubSquadPlayerResource;
use App\Models\Club;
use App\Models\Player;
use App\Support\GameContext;

class ClubController extends Controller
{
    public function __construct(private readonly GameContext $gameContext)
    {
    }
    public function show(int $clubId)
    {
        $club = Club::query()
            ->with(['stadium', 'account'])
            ->where('instance_id', $this->gameContext->instanceId())
            ->findOrFail($clubId);

        return ResponseHelper::success(
            new ClubResource($club)->toArray(request()),
            ResponseHelper::RESPONSE_SUCCESS_CODE
        );
    }

    public function squad(int $clubId)
    {
        Club::query()
            ->where('instance_id', $this->gameContext->instanceId())
            ->findOrFail($clubId);

        $players = Player::query()
            ->with('contract')
            ->where('instance_id', $this->gameContext->instanceId())
            ->where('club_id', $clubId)
            ->orderBy('position')
            ->orderBy('last_name')
            ->get();

        return ResponseHelper::success(
            ClubSquadPlayerResource::collection($players)->resolve(request()),
            ResponseHelper::RESPONSE_SUCCESS_CODE
        );
    }
}
