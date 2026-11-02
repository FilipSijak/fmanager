<?php

namespace App\Services\TrainingService;

use App\Services\TrainingService\Data\TrainingPlayerData;

class AiTrainingPlanner
{
    private const REST_BELOW_CONDITION = 90;

    public function plan(TrainingPlayerData $player, bool $playsTomorrow): TrainingPlan
    {
        if ($player->injured || $player->condition < self::REST_BELOW_CONDITION) {
            return $this->uniformPlan(TrainingIntensity::None);
        }

        if ($playsTomorrow) {
            return $this->uniformPlan(TrainingIntensity::Light);
        }

        return TrainingPlan::defaultForPosition($player->position);
    }

    private function uniformPlan(TrainingIntensity $intensity): TrainingPlan
    {
        return new TrainingPlan(array_fill_keys(['physical', 'tactical', 'technical', 'goalkeeping'], $intensity->value));
    }
}
