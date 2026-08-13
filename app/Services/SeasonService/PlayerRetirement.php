<?php

namespace App\Services\SeasonService;

use App\Models\Instance;
use App\Models\Player;
use App\Repositories\PlayerRepository;
use Carbon\CarbonImmutable;

class PlayerRetirement
{
    private const MINIMUM_RETIREMENT_AGE = 32;

    public function __construct(
        private readonly PlayerRepository $playerRepository,
        private readonly RetirementDecision $retirementDecision
    ) {}

    /** @return list<Player> */
    public function retireEligiblePlayers(Instance $instance): array
    {
        $asOfDate = CarbonImmutable::parse($instance->instance_date)->startOfDay();
        $cutoffDate = $asOfDate->subYears(self::MINIMUM_RETIREMENT_AGE);
        $retiredPlayers = [];

        $players = $this->playerRepository->playersEligibleForRetirement(
            (int) $instance->id,
            $cutoffDate->toDateString()
        );

        foreach ($players as $player) {
            if (! $this->retirementDecision->shouldRetire($player, $asOfDate)) {
                continue;
            }

            if ($this->playerRepository->retirePlayer((int) $player->id)) {
                $retiredPlayers[] = $player;
            }
        }

        return $retiredPlayers;
    }
}
