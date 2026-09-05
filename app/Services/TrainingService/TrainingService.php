<?php

namespace App\Services\TrainingService;

use App\Models\Instance;
use App\Repositories\TrainingRepository;
use App\Services\PersonService\PersonConfig\Player\PlayerFields;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class TrainingService
{
    private const REST_DAY_CONDITION_RECOVERY = 10;

    private const PLAYER_BATCH_SIZE = 50;

    public function __construct(
        private readonly TrainingRepository $trainingRepository,
        private readonly PlayerProgressCalculator $playerProgressCalculator
    ) {}

    public function processDay(Instance $instance): void
    {
        $trainingDate = CarbonImmutable::parse($instance->instance_date)->startOfDay();
        $previousDate = $trainingDate->subDay();
        $games = $this->trainingRepository->scheduledGames(
            (int) $instance->id,
            $previousDate,
            $trainingDate
        );
        $clubIds = static fn ($scheduledGames) => $scheduledGames
            ->flatMap(fn ($game): array => $game->clubIds())
            ->unique()
            ->values();
        $playingTodayIds = $clubIds($games->filter(
            fn ($game): bool => $game->kickoff->isSameDay($trainingDate)
        ));
        $postMatchRestIds = $clubIds($games->filter(
            fn ($game): bool => $game->kickoff->isSameDay($previousDate)
        ))->diff($playingTodayIds)->values();
        $clubsWithoutTraining = $playingTodayIds->merge($postMatchRestIds)->unique();

        $recoveryClubIds = $this->trainingRepository
            ->clubsByIds((int) $instance->id, $postMatchRestIds)
            ->pluck('id');
        $trainingClubIds = $this->trainingRepository
            ->clubsExceptIds((int) $instance->id, $clubsWithoutTraining)
            ->pluck('id');

        $this->trainingRepository->transaction(function () use (
            $instance,
            $recoveryClubIds,
            $trainingClubIds,
            $trainingDate
        ): void {
            $this->recoverCondition((int) $instance->id, $recoveryClubIds, $trainingDate);
            $this->executeTrainingSession((int) $instance->id, $trainingClubIds, $trainingDate);
        });
    }

    private function executeTrainingSession(
        int $instanceId,
        Collection $clubIds,
        CarbonImmutable $trainingDate
    ): void {
        if ($clubIds->isEmpty()) {
            return;
        }

        $trainingFields = array_merge(
            PlayerFields::TECHNICAL_FIELDS,
            PlayerFields::MENTAL_FIELDS,
            PlayerFields::PHYSICAL_FIELDS
        );
        $playerIds = $this->trainingRepository->playerIdsForTraining($instanceId, $clubIds);

        foreach ($playerIds->chunk(self::PLAYER_BATCH_SIZE) as $batchPlayerIds) {
            $this->executeTrainingBatch(
                $instanceId,
                $batchPlayerIds,
                $trainingFields,
                $trainingDate
            );
        }
    }

    private function executeTrainingBatch(
        int $instanceId,
        Collection $playerIds,
        array $trainingFields,
        CarbonImmutable $trainingDate
    ): void {
        $players = $this->trainingRepository->playersForTraining(
            $instanceId,
            $playerIds,
            $trainingFields,
            $trainingDate
        );
        $schedulesByPlayer = $this->trainingRepository->schedulesForPlayers($playerIds);
        $progressUpdates = [];
        $playerUpdates = [];

        foreach ($players as $player) {
            $updates = $this->playerProgressCalculator->forTrainingSession(
                $player,
                $schedulesByPlayer->get($player->id, []),
                $trainingFields,
                $trainingDate
            );
            $progressUpdates[(int) $player->id] = $updates->progress;

            if ($updates->player !== []) {
                $playerUpdates[(int) $player->id] = $updates->player;
            }
        }

        $this->trainingRepository->bulkUpdateProgress($progressUpdates);
        $this->trainingRepository->bulkUpdatePlayers($playerUpdates);
    }

    private function recoverCondition(
        int $instanceId,
        Collection $clubIds,
        CarbonImmutable $trainingDate
    ): void {
        if ($clubIds->isEmpty()) {
            return;
        }

        $this->trainingRepository->recoverClubsCondition(
            $instanceId,
            $clubIds,
            $trainingDate,
            self::REST_DAY_CONDITION_RECOVERY
        );
    }
}
