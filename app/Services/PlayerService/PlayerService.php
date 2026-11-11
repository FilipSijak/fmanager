<?php

namespace App\Services\PlayerService;

use App\Models\Club;
use App\Models\Instance;
use App\Models\Player;
use App\Models\Season;
use App\Repositories\PlayerRepository;
use App\Services\ContractService\ContractService;
use App\Services\PersonService\Data\GeneratedPlayerProfile;
use App\Services\PersonService\GeneratePeople\PlayerCreator;
use App\Services\PersonService\GeneratePeople\PlayerPotential;
use App\Services\PlayerService\Domain\PlayerAgeing;
use App\Services\PlayerService\Domain\PlayerPerformance;
use App\Support\GameContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class PlayerService
{
    public const int FREE_AGENTS_COUNT = 200;

    public const int FREE_AGENTS_POTENTIAL_LIMIT = 150;

    public function __construct(
        private readonly PlayerRepository $playerRepository,
        private readonly ContractService $contracts,
        private readonly PlayerCreator $playerCreator,
        private readonly PlayerPotential $playerPotential,
        private readonly GameContext $gameContext,
        private readonly PlayerAgeing $playerAgeing,
        private readonly PlayerPerformance $playerPerformance,
    ) {}

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
            if ($player->person === null) {
                continue;
            }

            $this->playerRepository->updatePotential($player, $this->playerAgeing->attributesOn($player, $asOfDate));
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

    /** @param list<Player> $generatedPlayers */
    private function persistGeneratedPlayers(?Club $club, array $generatedPlayers): void
    {
        DB::transaction(function () use ($club, $generatedPlayers): void {
            $players = $this->playerRepository->bulkPlayerInsert(
                $this->gameContext->instanceId(),
                $club,
                $generatedPlayers
            );

            $this->contracts->createInitialPlayerContracts($this->gameContext->instanceId(), $club, $players);
            $this->playerRepository->bulkAssignmentPlayersPositions($players);
        });
    }
}
