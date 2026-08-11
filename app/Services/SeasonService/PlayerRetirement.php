<?php

namespace App\Services\SeasonService;

use App\Models\Instance;
use App\Repositories\PlayerRepository;
use Carbon\CarbonImmutable;

class PlayerRetirement
{
    private const MINIMUM_RETIREMENT_AGE = 32;

    public function __construct(
        private readonly PlayerRepository $playerRepository,
        private readonly RetirementDecision $retirementDecision
    ) {}

    public function retireEligiblePlayers(Instance $instance): int
    {
        $asOfDate = CarbonImmutable::parse($instance->instance_date)->startOfDay();
        $cutoffDate = $asOfDate->subYears(self::MINIMUM_RETIREMENT_AGE);
        $retiredCount = 0;

        $players = $this->playerRepository->playersEligibleForRetirement(
            (int) $instance->id,
            $cutoffDate->toDateString()
        );

        foreach ($players as $player) {
            if (! $this->retirementDecision->shouldRetire($player, $asOfDate)) {
                continue;
            }

            if ($this->playerRepository->retirePlayer((int) $player->id)) {
                $retiredCount++;
            }
        }

        return $retiredCount;
    }
}
