<?php

namespace App\Services\TrainingService;

use App\Models\Club;
use App\Repositories\TrainingRepository;
use App\Services\PersonService\PersonConfig\Player\PlayerFields;

class TrainingService
{
    public function __construct(
        private readonly TrainingRepository $trainingRepository,
        private readonly PlayerProgress $playerProgress
    ) {}

    public function executeTrainingSession(Club $club): int
    {
        $trainingFields = array_merge(
            PlayerFields::TECHNICAL_FIELDS,
            PlayerFields::MENTAL_FIELDS,
            PlayerFields::PHYSICAL_FIELDS
        );

        return $this->trainingRepository->transaction(function () use ($club, $trainingFields): int {
            $trainingDate = $this->trainingRepository->trainingDate((int) $club->instance_id);
            $players = $this->trainingRepository->playersForTraining($club, $trainingFields, $trainingDate);
            $playerIds = $players->pluck('id');
            $progressByPlayer = $this->trainingRepository->progressForPlayers($playerIds, $trainingFields);
            $schedulesByPlayer = $this->trainingRepository->schedulesForPlayers($playerIds);

            $trainedPlayers = 0;

            foreach ($players as $player) {
                $progress = $progressByPlayer->get($player->id);

                if ($progress === null) {
                    continue;
                }

                $updates = $this->playerProgress->forTrainingSession(
                    $player,
                    $progress,
                    $schedulesByPlayer->get($player->id),
                    $trainingFields
                );
                $this->trainingRepository->updateProgress((int) $player->id, $updates->progress);

                if ($updates->player !== []) {
                    $this->trainingRepository->updatePlayer((int) $player->id, $updates->player);
                }

                if ($updates->countsAsTrained) {
                    $trainedPlayers++;
                }
            }

            return $trainedPlayers;
        });
    }

    public function recoverCondition(Club $club): int
    {
        return $this->trainingRepository->transaction(function () use ($club): int {
            $playerIds = $this->trainingRepository->activePlayerIds($club);
            $progressRows = $this->trainingRepository->conditionProgressForPlayers($playerIds);
            $recoveredPlayers = 0;

            foreach ($progressRows as $progress) {
                $condition = $this->playerProgress->recoveredCondition((int) $progress->condition);

                if ($condition === (int) $progress->condition) {
                    continue;
                }

                $this->trainingRepository->updateProgress((int) $progress->player_id, [
                    'condition' => $condition,
                    'updated_at' => now(),
                ]);
                $recoveredPlayers++;
            }

            return $recoveredPlayers;
        });
    }
}
