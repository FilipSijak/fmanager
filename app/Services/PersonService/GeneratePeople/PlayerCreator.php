<?php

namespace App\Services\PersonService\GeneratePeople;

use App\Models\Player;
use stdClass;

class PlayerCreator
{
    public function __construct(
        private readonly PlayerAttributesGenerator $attributesGenerator,
        private readonly PersonFactory $personFactory,
    ) {}

    public function create(stdClass $playerPotential, int $instanceId): Player
    {
        $generatedAttributes = $this->attributesGenerator->setPlayerDetails($playerPotential)->generateAttributes();

        return $this->personFactory->createPlayer($generatedAttributes, $instanceId);
    }
}
