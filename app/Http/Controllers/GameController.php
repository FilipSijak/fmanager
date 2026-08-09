<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Services\GameService\CompleteGameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function __construct(private readonly CompleteGameService $completeGameService) {}

    public function complete(Request $request, int $gameId): JsonResponse
    {
        $data = $request->validate([
            'home_team_goals' => ['required', 'integer', 'min:0'],
            'away_team_goals' => ['required', 'integer', 'min:0'],
        ]);

        return ResponseHelper::success($this->completeGameService->complete(
            $gameId,
            $data['home_team_goals'],
            $data['away_team_goals']
        )->toArray());
    }

    public function postpone(Request $request, int $gameId): JsonResponse
    {
        $data = $request->validate(['match_start' => ['required', 'date']]);

        return ResponseHelper::success(
            $this->completeGameService->postpone($gameId, $data['match_start'])->toArray()
        );
    }

    public function cancel(int $gameId): JsonResponse
    {
        return ResponseHelper::success($this->completeGameService->cancel($gameId)->toArray());
    }
}
