<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\UpdateTacticsRequest;
use App\Http\Resources\FormationResource;
use App\Http\Resources\TacticsResource;
use App\Models\Club;
use App\Models\Instance;
use App\Services\TacticsService\Data\TeamInstructionsData;
use App\Services\TacticsService\Mentality;
use App\Services\TacticsService\PassingStyle;
use App\Services\TacticsService\PressingIntensity;
use App\Services\TacticsService\TacklingStyle;
use App\Services\TacticsService\TacticsService;
use App\Support\GameContext;
use DomainException;
use Illuminate\Http\JsonResponse;

class TacticsController extends Controller
{
    public function __construct(
        private readonly GameContext $gameContext,
        private readonly TacticsService $tacticsService,
    ) {}

    public function show(): JsonResponse
    {
        $club = $this->managedClub();

        return ResponseHelper::success([
            'tactic' => (new TacticsResource($this->tacticsService->currentForClub($club)))->toArray(request()),
            'formations' => FormationResource::collection($this->tacticsService->availableFormations())->resolve(request()),
            'options' => $this->tacticsService->options(),
        ]);
    }

    public function update(UpdateTacticsRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $tactic = $this->tacticsService->saveForClub(
                $this->managedClub(),
                (int) $data['formation_id'],
                new TeamInstructionsData(
                    mentality: Mentality::from($data['mentality']),
                    pressing: PressingIntensity::from($data['pressing']),
                    passing: PassingStyle::from($data['passing']),
                    tackling: TacklingStyle::from($data['tackling']),
                    offsideTrap: (bool) $data['offside_trap'],
                    counterAttack: (bool) $data['counter_attack'],
                    menBehindBall: (bool) $data['men_behind_ball'],
                    freeKicksLeftPlayerId: $this->optionalPlayerId($data, 'free_kicks_left_player_id'),
                    freeKicksRightPlayerId: $this->optionalPlayerId($data, 'free_kicks_right_player_id'),
                    cornersLeftPlayerId: $this->optionalPlayerId($data, 'corners_left_player_id'),
                    cornersRightPlayerId: $this->optionalPlayerId($data, 'corners_right_player_id'),
                    playmakerPlayerId: $this->optionalPlayerId($data, 'playmaker_player_id'),
                ),
            );

            return ResponseHelper::success(
                (new TacticsResource($tactic))->toArray(request()),
                ResponseHelper::RESPONSE_SUCCESS_CODE,
            );
        } catch (DomainException $exception) {
            return ResponseHelper::error($exception->getMessage(), '', 422);
        }
    }

    /** @param array<string, mixed> $data */
    private function optionalPlayerId(array $data, string $field): ?int
    {
        return isset($data[$field]) ? (int) $data[$field] : null;
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
