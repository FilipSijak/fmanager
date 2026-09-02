<?php

namespace App\Services\SeasonService;

use App\Models\Instance;
use App\Models\Player;
use App\Services\PersonService\PersonService;
use Carbon\CarbonImmutable;

class UpdatePlayerPotentials
{
    public function __construct(
        private readonly PersonService $personService
    ) {}

    public function process(Instance $instance): void
    {
        $asOfDate = CarbonImmutable::parse($instance->instance_date)->startOfDay();

        Player::query()
            ->forInstance((int) $instance->id)
            ->active()
            ->with('person:id,dob')
            ->eachById(function (Player $player) use ($asOfDate): void {
                $this->personService->updatePlayerPotential($player, $asOfDate);
            });
    }
}
