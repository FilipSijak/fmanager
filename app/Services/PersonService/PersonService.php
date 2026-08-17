<?php

namespace App\Services\PersonService;

use App\Models\Club;
use App\Repositories\StaffRepository;
use App\Services\PersonService\GeneratePeople\PersonFactory;
use App\Services\PersonService\GeneratePeople\PlayerAttributesGenerator;
use App\Services\PersonService\GeneratePeople\PlayerPosition;
use App\Services\PersonService\GeneratePeople\StaffType\StaffCreator;
use App\Services\PersonService\PersonConfig\PersonTypes;

class PersonService implements IPersonService
{
    public function __construct(
        private readonly StaffRepository $staffRepository,
        private readonly StaffCreator $staffCreator,
    ) {}

    public function createPerson(\stdClass $playerPotential,int $instanceId, string $personType)
    {
        $personFactory = new PersonFactory();
        $person = null;

        switch ($personType) {
            case PersonTypes::PLAYER:
                $attributesGenerator = app(PlayerAttributesGenerator::class);
                $generatedAttributes = $attributesGenerator->setPlayerDetails($playerPotential)->generateAttributes();

                $person = $personFactory->createPlayer($generatedAttributes, $instanceId);
                break;
            case PersonTypes::MANAGER:

        }

        return $person;
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

    public function initialStaffClubSeed(int $instanceId, Club $club): void
    {
        $staffMembers = $this->staffCreator->generateForClubRank($club->rank);

        $this->staffRepository->bulkStaffInsert($instanceId, $club, $staffMembers);
    }

    public function generateFreeStaff(int $instanceId, int $count): void
    {
        $freeStaff = $this->staffCreator->generateFreeStaff($count);

        $this->staffRepository->bulkStaffInsert(
            $instanceId,
            null,
            $freeStaff
        );
    }
}
