<?php

namespace App\Services\PersonService;

use App\Contracts\Search\PlayerSearchIndexDispatcher;
use App\Domain\PlayerDevelopment\PlayerAttributeCeiling;
use App\Domain\PlayerDevelopment\PlayerDevelopmentCategory;
use App\Models\Club;
use App\Models\Instance;
use App\Models\Player;
use App\Models\Season;
use App\Models\StaffCoaching;
use App\Models\User;
use App\Repositories\PlayerRepository;
use App\Repositories\StaffRepository;
use App\Services\NewsService\NewsService;
use App\Services\NewsService\NewsTypes\PlayerContractNews;
use App\Services\PersonService\Data\GeneratedPlayerProfile;
use App\Services\PersonService\Data\PotentialByCategoryData;
use App\Services\PersonService\Domain\PlayerContractRenewal;
use App\Services\PersonService\Domain\PlayerPerformance;
use App\Services\PersonService\GeneratePeople\PlayerCreator;
use App\Services\PersonService\GeneratePeople\PlayerPotential;
use App\Services\PersonService\GeneratePeople\StaffType\StaffCreator;
use App\Services\PersonService\PersonConfig\PersonTypes;
use App\Services\PersonService\PersonConfig\Player\PlayerFields;
use App\Support\GameContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
        private readonly PlayerPerformance $playerPerformance,
        private readonly PlayerContractRenewal $contractRenewal,
        private readonly NewsService $news,
        private readonly PlayerContractNews $contractNews,
        private readonly PlayerSearchIndexDispatcher $playerSearch,
    ) {}

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function renewPlayerContract(int $playerId, array $data): array
    {
        return DB::transaction(function () use ($playerId, $data): array {
            $instance = $this->playerRepository->managedInstance($this->gameContext->instanceId(), true);
            abort_if($instance->club_id === null, 403);
            $date = (string) $instance->instance_date;
            $club = $instance->club;
            $player = $this->playerRepository->playerForRenewal($club, $playerId, $date, true);
            if ($data['expected_contract_end'] !== $player->contract->contract_end) {
                throw ValidationException::withMessages(['expected_contract_end' => 'The contract changed. Reload this screen before making another offer.']);
            }
            $months = (int) $data['months'];
            $endsAt = $this->contractRenewal->endsAt($date, $months, $player->contract->contract_end);
            $terms = $this->contractRenewal->terms($data);
            $account = $this->playerRepository->contractAccount($club);
            if ($terms['signing_fee'] > min((int) $account->balance, (int) $account->transfer_budget)) {
                throw ValidationException::withMessages(['transfer_fee' => 'The signing fee exceeds the available cash or transfer budget.']);
            }
            if ($this->playerRepository->annualContractWages($club, $player->id, $date) + $terms['salary'] * 52 > (int) $account->salaries_yearly_budget) {
                throw ValidationException::withMessages(['salary' => 'The salary exceeds the annual wage budget.']);
            }
            $decision = $this->contractRenewal->consider((int) $player->ambition, (int) $club->rank, $player->contract, $terms);
            if ($decision['status'] !== 'accepted') {
                $this->playerRepository->recordRenewalOffer($player, $terms, $date, $endsAt, $months, $decision['status']);

                $this->news->publish($this->contractNews->decision($player, $instance, $decision['message']));

                return $decision;
            }
            $this->playerRepository->applyContractRenewal($player, $account, $terms, $date, $endsAt, $months);

            $this->news->publish($this->contractNews->decision($player, $instance, 'Contract renewed until '.$endsAt.'.'));

            return ['status' => 'accepted', 'message' => 'Contract renewed until '.$endsAt.'.', 'ends_at' => $endsAt];
        });
    }

    public function processPlayerContracts(Instance $instance): void
    {
        DB::transaction(function () use ($instance): void {
            $instance = $this->playerRepository->managedInstance((int) $instance->id, true);
            $date = (string) $instance->instance_date;
            $reminderDate = CarbonImmutable::parse($date)->addMonthsNoOverflow(6)->toDateString();
            foreach ($this->playerRepository->contractsEndingBy((int) $instance->id, $reminderDate) as $player) {
                if ($player->contract->contract_end < $date) {
                    if ($player->club_id === $instance->club_id) {
                        $this->news->publish($this->contractNews->expired($player, $instance));
                    }
                    $this->playerRepository->releaseExpiredPlayer($player);
                    $this->playerSearch->synchronizePlayer((int) $player->id);

                    continue;
                }
                if ($player->club_id === $instance->club_id) {
                    if ($player->contract->renewal_reminded_for !== $player->contract->contract_end) {
                        $this->news->publish($this->contractNews->reminder($player, $instance));
                        $this->playerRepository->markContractReminder($player);
                    }

                    continue;
                }
                $this->renewAiPlayerContract($player, $date);
            }
        });
    }

    private function renewAiPlayerContract(Player $player, string $date): void
    {
        $club = $player->club;
        if ($club === null || $club->account === null) {
            return;
        }
        $terms = $this->contractRenewal->terms($player->contract->getAttributes());
        $terms['signing_fee'] = 0;
        $decision = $this->contractRenewal->consider((int) $player->ambition, (int) $club->rank, $player->contract, $terms);
        if ($decision['status'] === 'rejected') {
            return;
        }
        $terms['salary'] = $decision['counter_salary'] ?? $terms['salary'];
        $account = $this->playerRepository->contractAccount($club);
        if ($this->playerRepository->annualContractWages($club, $player->id, $date) + $terms['salary'] * 52 > (int) $account->salaries_yearly_budget) {
            return;
        }
        $endsAt = $this->contractRenewal->endsAt($date, 36, $player->contract->contract_end);
        $this->playerRepository->applyContractRenewal($player, $account, $terms, $date, $endsAt, 36);
    }

    /** @return array<string, mixed> */
    public function contractOverview(?int $playerId = null): array
    {
        $instance = $this->playerRepository->managedInstance($this->gameContext->instanceId());
        abort_if($instance->club_id === null, 403);
        $date = $this->gameContext->instanceDate();
        $club = $instance->club;
        $players = $playerId === null
            ? $this->playerRepository->clubContracts($club, $date)
            : collect([$this->playerRepository->playerForRenewal($club, $playerId, $date)]);
        $contracts = $players->map(function (Player $player) use ($date): array {
            $contract = $player->contract;
            $endsAt = CarbonImmutable::parse($contract->contract_end);

            return [
                'id' => $player->id, 'name' => trim($player->person->first_name.' '.$player->person->last_name),
                'position' => $player->position, 'contract_id' => $contract->id,
                'starts_at' => $contract->contract_start, 'ends_at' => $contract->contract_end,
                'expiring' => $endsAt->lte(CarbonImmutable::parse($date)->addMonthsNoOverflow(6)),
                'terms' => array_merge(['transfer_fee' => 0], collect(['salary', 'appearance', 'goal', 'assist', 'clean_sheet', 'league', 'promotion', 'cup', 'el', 'cl'])
                    ->mapWithKeys(fn (string $field): array => [$field => (int) $contract->{$field}])->all()),
            ];
        })->sortBy('ends_at')->values()->all();

        return ['club' => $club->only(['id', 'name']), 'date' => $date, 'players' => $contracts];
    }

    /** @return array{player: Player, fitness: array<string, mixed>, performance: array<string, mixed>} */
    public function playerProfile(int $playerId, ?int $seasonId = null): array
    {
        $player = $this->playerRepository->profile($this->gameContext->instanceId(), $playerId);
        $date = $this->gameContext->instanceDate();
        $seasons = $this->playerRepository->performanceSeasons($player, $date);
        $currentSeasonId = $this->gameContext->hasSeasonId() ? $this->gameContext->seasonId() : null;

        if ($seasonId !== null && ! $seasons->contains('id', $seasonId)) {
            throw (new ModelNotFoundException)->setModel(Season::class, [$seasonId]);
        }

        $seasonId ??= $currentSeasonId;

        return [
            'player' => $player,
            'fitness' => $this->playerPerformance->fitness(
                $this->playerRepository->fitnessProgress($player),
                $this->playerRepository->activeInjuries($player, $date),
            ),
            'performance' => $this->playerPerformance->summary(
                $seasonId,
                $this->playerRepository->performanceByCompetition($player, $seasonId, $date),
                $this->playerRepository->recentAppearances($player, $seasonId, $date),
            ) + [
                'seasons' => $this->playerPerformance->seasons($seasons),
                'current_season_id' => $currentSeasonId,
            ],
        ];
    }

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
            $manager->reputation = $club->managerReputationTarget();

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
     * Free the user's manager from their club and end the contract, leaving the club vacant for the next appointment.
     */
    public function releaseUserManager(StaffCoaching $userManager): void
    {
        DB::transaction(function () use ($userManager): void {
            $contractId = $userManager->contract_id;
            $userManager->club_id = null;
            $userManager->contract_id = null;
            $userManager->save();

            if ($contractId !== null) {
                DB::table('staff_contracts')->where('id', $contractId)->delete();
            }
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
