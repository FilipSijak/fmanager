<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Instance;
use App\Services\InstanceService\InstanceService;
use App\Services\MatchEngineService\LiveMatchService;
use App\Services\PersonService\PersonService;
use App\Support\GameContext;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstanceController extends Controller
{
    private InstanceService $instanceService;

    public function __construct(
        InstanceService $instanceService,
        private readonly LiveMatchService $liveMatchService,
        private readonly GameContext $gameContext,
    ) {
        $this->instanceService = $instanceService;
    }

    /**
     * Moves the game on a day, unless the user's club plays today: then its match is kicked off (or resumed) to be
     * played live first, and the day only moves on once it has finished.
     */
    public function nextDay(): JsonResponse
    {
        $instance = Instance::query()->findOrFail($this->gameContext->instanceId());
        $userGame = $this->liveMatchService->userGameToday($instance);

        if ($userGame !== null) {
            $this->liveMatchService->start($userGame, $instance);

            return ResponseHelper::success(['date' => $instance->instance_date, 'live_game_id' => $userGame->id]);
        }

        try {
            $instance = $this->instanceService->nextDay();
        } catch (DomainException $exception) {
            return ResponseHelper::error($exception->getMessage(), '', 422);
        }

        return ResponseHelper::success(['date' => $instance->instance_date, 'live_game_id' => null]);
    }

    public function startNewGame(Request $request): JsonResponse
    {
        try {
            $instance = $this->instanceService->createNewInstance($request->user());

            return ResponseHelper::success(
                $instance->toArray(),
                201
            );
        } catch (\Throwable $exception) {
            report($exception);

            return ResponseHelper::error(
                'Failed to create new instance',
                '',
                ResponseHelper::RESPONSE_ERROR_CODE
            );
        }
    }

    public function resign(Request $request, GameContext $gameContext, PersonService $personService): JsonResponse
    {
        $instance = $this->instanceService->resignFromClub($request->user(), $gameContext->instanceId(), $personService);

        return ResponseHelper::success($instance->toArray());
    }
}
