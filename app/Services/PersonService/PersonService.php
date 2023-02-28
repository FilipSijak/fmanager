<?php

namespace App\Services\PersonService;

use App\Services\PersonService\GeneratePeople\PersonFactory;
use App\Services\PersonService\GeneratePeople\PlayerAttributesGenerator;
use App\Services\PersonService\GeneratePeople\PlayerPosition;
use App\Services\PersonService\PersonConfig\PersonTypes;

class PersonService
{
    public function createPerson(\stdClass $playerPotential,int $instanceId, string $personType, int $clubAcademyRank)
    {
        $personFactory = new PersonFactory();

        switch ($personType) {
            case PersonTypes::PLAYER:
                $attributesGenerator = new PlayerAttributesGenerator();
                $generatedAttributes = $attributesGenerator->generateAttributes($playerPotential);

                $player = $personFactory->createPlayer($generatedAttributes, $instanceId);
                break;
        }

        return $player;
    }

    /**
     * @param array $playerAttributes
     *
     * @return array
     */
    public function generatePlayerPositionList(array $playerAttributes): array
    {
        $playerPosition = new PlayerPosition();

        return $playerPosition->getInitialPositionsBasedOnAttributes($playerAttributes);
    }
}
