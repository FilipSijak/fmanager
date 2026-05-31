<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\ClubResource;
use App\Models\Club;
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
            (new ClubResource($club))->toArray(request()),
            ResponseHelper::RESPONSE_SUCCESS_CODE
        );
    }
}
