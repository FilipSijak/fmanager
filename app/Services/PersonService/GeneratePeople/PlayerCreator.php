<?php

namespace App\Services\PersonService\GeneratePeople;

use App\Models\Player;
use App\Services\PersonService\Data\GeneratedPlayerProfile;
use App\Services\PersonService\Data\PersonInfo;
use Carbon\CarbonInterface;

class PlayerCreator
{
    public function __construct(
        private readonly PlayerAttributesGenerator $attributesGenerator,
        private readonly PersonFactory $personFactory,
    ) {}

    public function create(
        GeneratedPlayerProfile $playerPotential,
        int $instanceId,
        CarbonInterface $asOfDate,
        ?PersonInfo $personDetails = null,
    ): Player {
        $generatedAttributes = $this->attributesGenerator->setPlayerDetails($playerPotential, $personDetails)->generateAttributes($asOfDate);

        return $this->personFactory->createPlayer($generatedAttributes, $instanceId);
    }
}
