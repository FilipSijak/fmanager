<?php

namespace App\Services\PersonService\GeneratePeople;

use App\Models\Player;
use LogicException;
use stdClass;

class PlayerCreator
{
    public function __construct(
        private readonly PlayerAttributesGenerator $attributesGenerator,
        private readonly PersonFactory $personFactory,
    ) {}

    public function create(stdClass $playerPotential, int $instanceId): Player
    {
        throw new LogicException('Player creation has not been implemented.');
    }
}
