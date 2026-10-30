<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveSquadLineupRequest;
use App\Http\Resources\ClubTacticPlayerSlotResource;
use App\Models\Club;
use App\Models\Instance;
use App\Services\ClubService\ClubService;
use App\Support\GameContext;
use DomainException;
use Illuminate\Http\JsonResponse;

class SquadLineupController extends Controller
{
    public function __construct(
        private readonly GameContext $gameContext,
        private readonly ClubService $clubService,
    ) {}

    public function show(): JsonResponse
    {
        return ResponseHelper::success(
            ClubTacticPlayerSlotResource::collection(
                $this->clubService->currentLineup($this->managedClub()),
            )->resolve(request()),
        );
    }

    public function store(SaveSquadLineupRequest $request): JsonResponse
    {
        try {
            $slots = $this->clubService->saveLineup(
                $this->managedClub(),
                $request->validated('assignments'),
            );

            return ResponseHelper::success(
                ClubTacticPlayerSlotResource::collection($slots)->resolve(request()),
            );
        } catch (DomainException $exception) {
            return ResponseHelper::error($exception->getMessage(), '', 422);
        }
    }

    private function managedClub(): Club
    {
        $instance = Instance::query()->findOrFail($this->gameContext->instanceId());

        return Club::query()
            ->whereKey($instance->club_id)
            ->where('instance_id', $instance->id)
            ->firstOrFail();
    }
}
