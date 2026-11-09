<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Requests\BrowseSeasonRequest;
use App\Http\Resources\PlayerResource;
use App\Services\PersonService\PersonService;
use Illuminate\Http\JsonResponse;

class PlayerController extends CoreController
{
    public function __construct(private readonly PersonService $personService) {}

    public function show(BrowseSeasonRequest $request, int $playerId): JsonResponse
    {
        $profile = $this->personService->playerProfile(
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
