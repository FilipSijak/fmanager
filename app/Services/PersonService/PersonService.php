<?php

namespace App\Services\PersonService;

use App\Models\Club;
use App\Models\Instance;
use App\Models\Player;
use App\Repositories\PlayerRepository;
use App\Repositories\StaffRepository;
use App\Services\PersonService\Data\GeneratedPlayerProfile;
use App\Services\PersonService\GeneratePeople\PlayerCreator;
use App\Services\PersonService\GeneratePeople\PlayerPotential;
use App\Services\PersonService\GeneratePeople\StaffType\StaffCreator;
use App\Support\GameContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class PersonService
{
    const int FREE_AGENTS_COUNT = 200;

    const int FREE_AGENTS_POTENTIAL_LIMIT = 150;

    public function __construct(
        private readonly StaffRepository $staffRepository,
        private readonly PlayerRepository $playerRepository,
        private readonly PlayerCreator $playerCreator,
        private readonly StaffCreator $staffCreator,
        private readonly PlayerPotential $playerPotential,
        private readonly GameContext $gameContext,
    ) {}

    public function createPlayer(GeneratedPlayerProfile $playerPotential): Player
    {
        return $this->playerCreator->create(
            $playerPotential,
            $this->gameContext->instanceId()
        );
    }

    public function updatePlayerPotentials(Instance $instance): void
    {
        $asOfDate = CarbonImmutable::parse($instance->instance_date)->startOfDay();

        foreach ($this->playerRepository->activePlayers((int) $instance->id) as $player) {
            $this->updatePlayerPotential($player, $asOfDate);
        }
    }

    private function updatePlayerPotential(Player $player, CarbonInterface $asOfDate): void
    {
        if ($player->person === null) {
            return;
        }

        $player->potential = $this->playerPotential->onDate(
            (int) $player->max_potential,
            CarbonImmutable::parse($player->person->dob),
            $asOfDate
        );
        $player->save();
    }

    public function createPlayersForClub(Club $club): void
    {
        $playersPotentialWithPosition = $this->playerPotential->createForClubRank($club->rank_academy);
        $generatedPlayers = [];

        foreach ($playersPotentialWithPosition as $playerPotential) {
            $player = $this->createPlayer($playerPotential);

            $generatedPlayers[] = $player;
        }

        $this->persistGeneratedPlayers($club, $generatedPlayers);
    }

    public function createFreePlayers(int $count = self::FREE_AGENTS_COUNT): void
    {
        $generatedPlayers = [];

        for ($i = 0; $i < $count; $i++) {
            $playerWithPositionAndPotential = $this->playerPotential->createFreeAgent(self::FREE_AGENTS_POTENTIAL_LIMIT);

            $generatedPlayers[] = $this->createPlayer($playerWithPositionAndPotential);
        }

        $this->persistGeneratedPlayers(null, $generatedPlayers);
    }

    private function persistGeneratedPlayers(?Club $club, array $generatedPlayers): void
    {
        DB::transaction(function () use ($club, $generatedPlayers): void {
            $players = $this->playerRepository->bulkPlayerInsert(
                $this->gameContext->instanceId(),
                $club,
                $generatedPlayers
            );

            $this->playerRepository->bulkAssignmentPlayersPositions($players);
        });
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
