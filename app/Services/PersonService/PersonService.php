<?php

namespace App\Services\PersonService;

use App\Models\Club;
use App\Models\Player;
use App\Repositories\StaffRepository;
use App\Services\PersonService\GeneratePeople\PersonFactory;
use App\Services\PersonService\GeneratePeople\PlayerAttributesGenerator;
use App\Services\PersonService\GeneratePeople\PlayerCreator;
use App\Services\PersonService\GeneratePeople\PlayerPosition;
use App\Services\PersonService\GeneratePeople\PlayerPotential;
use App\Services\PersonService\GeneratePeople\StaffType\StaffCreator;
use App\Services\PersonService\PersonConfig\PersonTypes;
use App\Support\GameContext;

class PersonService implements IPersonService
{
    const FREE_AGENTS_COUNT = 200;

    const FREE_AGENTS_POTENTIAL_LIMIT = 150;

    public function __construct(
        private readonly StaffRepository $staffRepository,
        private readonly PlayerCreator $playerCreator,
        private readonly StaffCreator $staffCreator,
        private readonly PlayerPotential $playerPotential,
        private readonly GameContext $gameContext,
    ) {}

    public function createPerson(\stdClass $playerPotential, string $personType)
    {
        $personFactory = new PersonFactory();
        $person = null;

        switch ($personType) {
            case PersonTypes::PLAYER:
                $attributesGenerator = app(PlayerAttributesGenerator::class);
                $generatedAttributes = $attributesGenerator->setPlayerDetails($playerPotential)->generateAttributes();

                $person = $personFactory->createPlayer(
                    $generatedAttributes,
                    $this->gameContext->instanceId()
                );
                break;
            case PersonTypes::MANAGER:

        }

        return $person;
    }

    public function createPlayer(\stdClass $playerPotential): Player
    {
        return $this->playerCreator->create(
            $playerPotential,
            $this->gameContext->instanceId()
        );
    }

    public function createPlayersForClub(Club $club): array
    {
        $academyRank = $club->rank_academy;
        $playerPotentialList = $this->playerPotential->getPlayerPotential($academyRank);
        $playersPotentialWithPosition = $this->playerPotential->assignPlayerPositions($playerPotentialList);
        $generatedPlayers = [];

        foreach ($playersPotentialWithPosition as $playerPotential) {
            $player = $this->createPlayer($playerPotential);

            $generatedPlayers[] = $player;
        }

        return $generatedPlayers;
    }

    public function createFreePlayers(int $count = self::FREE_AGENTS_COUNT)
    {
        $generatedPlayers = [];

        for ($i = 0; $i < $count; $i++) {
            $playerWithPositionAndPotential = $this->playerPotential->generateFreeAgent(self::FREE_AGENTS_POTENTIAL_LIMIT);

            $generatedPlayers[] = $this->createPlayer($playerWithPositionAndPotential);
        }

        return $generatedPlayers;
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

    public function initialStaffClubSeed(Club $club): void
    {
        $staffMembers = $this->staffCreator->generateForClubRank($club->rank);

        $this->staffRepository->bulkStaffInsert(
            $this->gameContext->instanceId(),
            $club,
            $staffMembers
        );
    }

    public function generateFreeStaff(int $count): void
    {
        $freeStaff = $this->staffCreator->generateFreeStaff($count);

        $this->staffRepository->bulkStaffInsert(
            $this->gameContext->instanceId(),
            null,
            $freeStaff
        );
    }
}
