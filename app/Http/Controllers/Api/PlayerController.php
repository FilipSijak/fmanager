<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Requests\BrowseSeasonRequest;
use App\Http\Resources\PlayerResource;
use App\Services\PlayerService\PlayerService;
use Illuminate\Http\JsonResponse;

class PlayerController extends CoreController
{
    public function __construct(private readonly PlayerService $playerService) {}

    public function show(BrowseSeasonRequest $request, int $playerId): JsonResponse
    {
        $profile = $this->playerService->playerProfile(
            $playerId,
            $request->filled('season_id') ? $request->integer('season_id') : null,
        );

        return ResponseHelper::success(
            (new PlayerResource($profile['player']))->toArray($request) + [
                'fitness' => $profile['fitness'],
                'performance' => $profile['performance'],
            ],
            ResponseHelper::RESPONSE_SUCCESS_CODE
        );
    }
}
