<?php

namespace App\Services\PersonService;

use App\Domain\PlayerDevelopment\PlayerAttributeCeiling;
use App\Domain\PlayerDevelopment\PlayerDevelopmentCategory;
use App\Models\Club;
use App\Models\Instance;
use App\Models\Player;
use App\Models\StaffCoaching;
use App\Models\User;
use App\Repositories\PlayerRepository;
use App\Repositories\StaffRepository;
use App\Services\PersonService\Data\GeneratedPlayerProfile;
use App\Services\PersonService\Data\PotentialByCategoryData;
use App\Services\PersonService\GeneratePeople\PlayerCreator;
use App\Services\PersonService\GeneratePeople\PlayerPotential;
use App\Services\PersonService\GeneratePeople\StaffType\StaffCreator;
use App\Services\PersonService\PersonConfig\PersonTypes;
use App\Services\PersonService\PersonConfig\Player\PlayerFields;
use App\Support\GameContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
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
        private readonly PlayerAttributeCeiling $playerAttributeCeiling,
    ) {}

    public function createPlayer(GeneratedPlayerProfile $playerPotential): Player
    {
        return $this->playerCreator->create(
            $playerPotential,
            $this->gameContext->instanceId(),
            CarbonImmutable::parse($this->gameContext->instanceDate())->startOfDay()
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

        $dateOfBirth = CarbonImmutable::parse($player->person->dob);
        $player->potential = $this->playerPotential->onDate(
            (int) $player->max_potential,
            $dateOfBirth,
            $asOfDate
        );
        $currentCategoryPotentials = $this->playerPotential->potentialByCategoryOnDate(
            new PotentialByCategoryData(
                technical: (int) $player->technical,
                mental: (int) $player->mental,
                physical: (int) $player->physical,
            ),
            $dateOfBirth,
            $asOfDate
        );
        $player->current_technical_potential = $currentCategoryPotentials->technical;
        $player->current_mental_potential = $currentCategoryPotentials->mental;
        $player->current_physical_potential = $currentCategoryPotentials->physical;
        $this->reduceAttributesToCurrentPotential($player, $currentCategoryPotentials);
        $player->save();
    }

    private function reduceAttributesToCurrentPotential(
        Player $player,
        PotentialByCategoryData $currentCategoryPotentials
    ): void {
        foreach (PlayerDevelopmentCategory::cases() as $category) {
            $fields = $category->fields();
            $categoryPotential = $currentCategoryPotentials->{$category->value};
            $categoryCeiling = $this->playerAttributeCeiling->forPotential($categoryPotential);

            foreach ($fields as $field) {
                $player->{$field} = min((int) $player->{$field}, $categoryCeiling);
            }
        }

        if ($player->position === 'GK') {
            $goalkeeperCeiling = $this->playerAttributeCeiling->forPotential($currentCategoryPotentials->technical);

            foreach (PlayerFields::GOALKEEPING_FIELDS as $field) {
                $player->{$field} = min((int) $player->{$field}, $goalkeeperCeiling);
            }
        }
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

    public function assignUserManager(Club $club, User $user): StaffCoaching
    {
        return DB::transaction(function () use ($club, $user): StaffCoaching {
            $previousManager = StaffCoaching::query()
                ->forInstance($club->instance_id)
                ->where('club_id', $club->id)
                ->where('type', PersonTypes::MANAGER)
                ->active()
                ->lockForUpdate()
                ->firstOrFail();

            $nameParts = explode(' ', trim($user->name), 2);
            $person = $previousManager->person->replicate();
            $person->first_name = $nameParts[0];
            $person->last_name = $nameParts[1] ?? '';
            $person->save();

            $manager = $previousManager->replicate();
            $manager->setRelations([]);
            $manager->person_id = $person->id;

            foreach ([
                'attacking', 'defending', 'fitness', 'mental', 'tactical', 'technical',
                'working_with_youngsters', 'adaptability', 'determination', 'discipline',
                'man_management', 'motivating', 'judging_player_potential', 'judging_player_ability',
                'judging_staff_ability', 'negotiating', 'tactics', 'distribution', 'handling', 'shot_stopping',
            ] as $attribute) {
                $manager->{$attribute} = 1;
            }

            $manager->save();

            if ($previousManager->tacticPreference !== null) {
                $preference = $previousManager->tacticPreference->replicate();
                $preference->staff_coaching_id = $manager->id;
                $preference->save();
            }

            $previousManager->club_id = null;
            $previousManager->contract_id = null;
            $previousManager->save();

            return $manager;
        });
    }

    /**
     * Free the user's manager from the club and hand the club, with the user's contract, to the best free AI manager.
     *
     * @throws DomainException when no free AI manager can take over the club
     */
    public function releaseUserManager(Club $club, StaffCoaching $userManager): StaffCoaching
    {
        return DB::transaction(function () use ($club, $userManager): StaffCoaching {
            $replacement = StaffCoaching::query()
                ->forInstance($club->instance_id)
                ->whereNull('club_id')
                ->where('type', PersonTypes::MANAGER)
                ->active()
                ->aiControlled()
                ->orderByDesc('coaching_potential')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($replacement === null) {
                throw new DomainException('No free manager is available to take over the club.');
            }

            $replacement->club_id = $club->id;
            $replacement->contract_id = $userManager->contract_id;
            $replacement->save();

            $userManager->club_id = null;
            $userManager->contract_id = null;
            $userManager->save();

            return $replacement;
        });
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
