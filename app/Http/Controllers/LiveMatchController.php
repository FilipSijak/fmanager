<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\AdvanceLiveMatchRequest;
use App\Http\Requests\ChangeLiveMatchTacticsRequest;
use App\Http\Requests\SubstituteLiveMatchRequest;
use App\Models\Instance;
use App\Services\MatchEngineService\LiveMatchService;
use App\Services\TacticsService\Mentality;
use App\Support\GameContext;
use DomainException;
use Illuminate\Http\JsonResponse;

class LiveMatchController extends Controller
{
    public function __construct(
        private readonly LiveMatchService $liveMatchService,
        private readonly GameContext $gameContext,
    ) {}

    public function show(int $gameId): JsonResponse
    {
        return $this->respond(fn (): array => $this->liveMatchService->view(
            $this->liveMatchService->find($gameId, $this->instance()),
        ));
    }

    public function advance(int $gameId, AdvanceLiveMatchRequest $request): JsonResponse
    {
        return $this->respond(fn (): array => $this->liveMatchService->view($this->liveMatchService->advance(
            $this->liveMatchService->find($gameId, $this->instance()),
            $request->integer('minutes'),
        )));
    }

    public function substitute(int $gameId, SubstituteLiveMatchRequest $request): JsonResponse
    {
        return $this->respond(fn (): array => $this->liveMatchService->view($this->liveMatchService->substitute(
            $this->liveMatchService->find($gameId, $this->instance()),
            $request->integer('player_off_id'),
            $request->integer('player_on_id'),
        )));
    }

    public function tactics(int $gameId, ChangeLiveMatchTacticsRequest $request): JsonResponse
    {
        return $this->respond(fn (): array => $this->liveMatchService->view($this->liveMatchService->changeTactics(
            $this->liveMatchService->find($gameId, $this->instance()),
            $request->integer('formation_id'),
            $request->enum('mentality', Mentality::class),
            $request->assignments(),
        )));
    }

    /** @param callable(): array<string, mixed> $action */
    private function respond(callable $action): JsonResponse
    {
        try {
            return ResponseHelper::success($action());
        } catch (DomainException $exception) {
            return ResponseHelper::error($exception->getMessage(), '', 422);
        }
    }

    private function instance(): Instance
    {
        return Instance::query()->findOrFail($this->gameContext->instanceId());
    }
}
