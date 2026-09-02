<?php

namespace App\Services\SeasonService;

use App\Models\Instance;
use App\Models\Player;
use App\Services\PersonService\GeneratePeople\PlayerPotentialByAge;
use Carbon\CarbonImmutable;

class UpdatePlayerPotentials
{
    public function __construct(
        private readonly PlayerPotentialByAge $playerPotentialByAge
    ) {}

    public function process(Instance $instance): void
    {
        $asOfDate = CarbonImmutable::parse($instance->instance_date)->startOfDay();

        Player::query()
            ->forInstance((int) $instance->id)
            ->active()
            ->with('person:id,dob')
            ->eachById(function (Player $player) use ($asOfDate): void {
                if ($player->person === null) {
                    return;
                }

                $player->potential = $this->playerPotentialByAge->calculateOnDate(
                    (int) $player->max_potential,
                    CarbonImmutable::parse($player->person->dob),
                    $asOfDate
                );
                $player->save();
            });
    }
}
