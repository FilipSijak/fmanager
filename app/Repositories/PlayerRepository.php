<?php

namespace App\Repositories;

use App\Helpers\DisplayHelpers;
use App\Models\Club;
use App\Models\GamePlayerStat;
use App\Models\Instance;
use App\Models\Player;
use App\Models\PlayerInjury;
use App\Models\Season;
use App\Repositories\Interfaces\IPlayerRepository;
use App\Services\PersonService\DataLayer\PlayerDataSource;
use App\Services\PersonService\GeneratePeople\PlayerPosition;
use App\Services\PersonService\PersonConfig\Player\PlayerFields;
use App\Services\PersonService\PersonConfig\Player\PlayerPositionConfig;
use App\Services\TransferService\TransferStatusTypes;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use RuntimeException;
use stdClass;

class PlayerRepository implements IPlayerRepository
{
    private const INSERT_CHUNK_SIZE = 250;

    private const PLAYER_ATTRIBUTE_COLUMNS = [
        'corners', 'crossing', 'dribbling', 'finishing', 'first_touch', 'freeKick',
        'heading', 'long_shots', 'long_throws', 'marking', 'passing', 'penalty_taking',
        'tackling', 'technique', 'aggression', 'anticipation', 'bravery', 'composure',
        'concentration', 'creativity', 'decisions', 'determination', 'flair', 'leadership',
        'of_the_ball', 'positioning', 'teamwork', 'workrate', 'acceleration', 'agility',
        'balance', 'jumping', 'natural_fitness', 'pace', 'stamina', 'strength',
    ];

    private PlayerDataSource $playerDataSource;

    public function __construct(PlayerDataSource $playerDataSource)
    {
        $this->playerDataSource = $playerDataSource;
    }

    public function managedInstance(int $instanceId, bool $lockForUpdate = false): Instance
    {
        return Instance::query()->when($lockForUpdate, fn ($query) => $query->lockForUpdate())->findOrFail($instanceId);
    }

    /** @return EloquentCollection<int, Player> */
    public function clubContracts(Club $club, string $date): EloquentCollection
    {
        return Player::query()->forClub($club)->withCurrentContractOn($date)
            ->with(['person', 'contract'])->orderBy('players.id')->get();
    }

    public function playerForRenewal(Club $club, int $playerId, string $date, bool $lockForUpdate = false): Player
    {
        return Player::query()->forClub($club)->withCurrentContractOn($date)
            ->with(['person', 'contract'])->when($lockForUpdate, fn ($query) => $query->lockForUpdate())
            ->findOrFail($playerId);
    }

    public function profile(int $instanceId, int $playerId): Player
    {
        return Player::query()->forInstance($instanceId)
            ->with(['person', 'club', 'contract'])->findOrFail($playerId);
    }

    /** @return EloquentCollection<int, Season> */
    public function performanceSeasons(Player $player, string $date): EloquentCollection
    {
        return Season::query()->where('instance_id', $player->instance_id)
            ->whereDate('start_date', '<=', $date)->orderByDesc('start_date')->orderByDesc('id')
            ->get(['id', 'start_date', 'end_date']);
    }

    public function fitnessProgress(Player $player): ?stdClass
    {
        return DB::table('players_progress')->where('player_id', $player->id)
            ->where('instance_id', $player->instance_id)->first(['condition', 'morale']);
    }

    /** @return EloquentCollection<int, PlayerInjury> */
    public function activeInjuries(Player $player, string $date): EloquentCollection
    {
        return PlayerInjury::query()->forPlayer($player)->activeOn($date)
            ->join('injuries', 'injuries.id', '=', 'player_injuries.injury_id')
            ->orderBy('injury_end_date')->orderBy('player_injuries.id')
            ->get(['injuries.type', 'injury_start_date', 'injury_end_date']);
    }

    /** @return EloquentCollection<int, GamePlayerStat> */
    public function recentAppearances(Player $player, ?int $seasonId, string $date): EloquentCollection
    {
        return GamePlayerStat::query()->forPlayer($player)->completedAppearances($seasonId, $date)
            ->join('clubs as home', function (JoinClause $join): void {
                $join->on('home.id', '=', 'games.hometeam_id')->on('home.instance_id', '=', 'games.instance_id');
            })
            ->join('clubs as away', function (JoinClause $join): void {
                $join->on('away.id', '=', 'games.awayteam_id')->on('away.instance_id', '=', 'games.instance_id');
            })
            ->select(['game_player_stats.*', 'games.match_start', 'games.hometeam_id',
                'games.home_team_goals', 'games.away_team_goals', 'home.name as home_name', 'away.name as away_name'])
            ->orderByDesc('games.match_start')->orderByDesc('games.id')->limit(5)->get();
    }

    /** @return Collection<int, stdClass> */
    public function performanceByCompetition(Player $player, ?int $seasonId, string $date): Collection
    {
        return GamePlayerStat::query()->forPlayer($player)->completedAppearances($seasonId, $date)
            ->join('competitions', function (JoinClause $join): void {
                $join->on('competitions.id', '=', 'games.competition_id')
                    ->on('competitions.instance_id', '=', 'games.instance_id');
            })
            ->select(['competitions.id', 'competitions.name'])
            ->selectRaw('COUNT(*) as appearances, SUM(minutes_played) as minutes, SUM(goals) as goals, SUM(assists) as assists, AVG(rating) as average_rating')
            ->groupBy('competitions.id', 'competitions.name')->orderBy('competitions.name')->orderBy('competitions.id')
            ->toBase()->get();
    }

    /** @return EloquentCollection<int, Player> */
    public function bulkPlayerInsert(
        int $instanceId,
        ?Club $club,
        array $generatedPlayers
    ): EloquentCollection {
        return DB::transaction(function () use ($instanceId, $club, $generatedPlayers): EloquentCollection {
            $instanceDate = DB::table('instances')->where('id', $instanceId)->value('instance_date');

            if ($instanceDate === null) {
                throw new RuntimeException("Instance {$instanceId} does not exist.");
            }

            $personIds = [];
            $playerRows = [];

            foreach ($generatedPlayers as $player) {
                [$marketingRank, $playerValue] = $this->marketingRankAndValue($player, $club);
                $player->marketing_rank = $marketingRank;

                $personId = DB::table('people')->insertGetId([
                    'instance_id' => $instanceId,
                    'first_name' => $player->first_name,
                    'last_name' => $player->last_name,
                    'country_code' => $player->country_code,
                    'dob' => $player->dob,
                ]);

                $contractId = DB::table('players_contracts')->insertGetId(
                    $this->playerDataSource->generatedContractData($player, (string) $instanceDate)
                );

                $personIds[] = $personId;
                $playerRows[] = $this->playerInsertRow(
                    $player,
                    $instanceId,
                    $personId,
                    $contractId,
                    $club?->id,
                    $playerValue
                );
            }

            foreach (array_chunk($playerRows, self::INSERT_CHUNK_SIZE) as $chunk) {
                DB::table('players')->insert($chunk);
            }

            $players = Player::query()->whereIn('person_id', $personIds)->get();
            $players->each->initializeProgress();

            return $players;
        });
    }

    public function bulkAssignmentPlayersPositions($players): void
    {
        $playerPositionGenerator = new PlayerPosition;
        $playerPositionsData = [];

        foreach ($players as $player) {

            $attributes = $player->getAttributes();
            $positionList = $playerPositionGenerator->getInitialPositionsBasedOnAttributes($attributes, $player->position);
            $playerPositions = array_flip(PlayerPositionConfig::PLAYER_POSITIONS);

            foreach ($positionList as $position => $grade) {
                $playerPositionsData[] = [
                    'player_id' => $player->id,
                    'position_id' => $playerPositions[$position],
                    'position_grade' => $grade,
                ];
            }
        }

        foreach (array_chunk($playerPositionsData, self::INSERT_CHUNK_SIZE) as $chunk) {
            DB::table('player_position')->insert($chunk);
        }
    }

    private function marketingRankAndValue(Player $player, ?Club $club): array
    {
        if ($club === null) {
            return [$player->potential, 0];
        }

        $clubRank = $club->rank * 10;
        $marketingRank = $clubRank > $player->potential
            ? $player->potential + (($clubRank - $player->potential) / 2)
            : $player->potential - (($player->potential - $clubRank) / 2);

        $player->marketing_rank = $marketingRank;

        return [$marketingRank, $this->calculatePlayerValueWithinClub($player)];
    }

    private function playerInsertRow(
        Player $player,
        int $instanceId,
        int $personId,
        int $contractId,
        ?int $clubId,
        int $playerValue
    ): array {
        $attributesCategories = $player->getAttributeCategoriesPotential();
        $row = [
            'instance_id' => $instanceId,
            'person_id' => $personId,
            'club_id' => $clubId,
            'contract_id' => $contractId,
            'value' => $playerValue,
            'marketing_rank' => $player->marketing_rank,
            'potential' => $player->potential,
            'max_potential' => $player->max_potential,
            'ambition' => rand((int) floor($player->potential / 10), 20),
            'loyalty' => rand(1, 20),
            'position' => $player->position,
            'technical' => $attributesCategories->technical,
            'mental' => $attributesCategories->mental,
            'physical' => $attributesCategories->physical,
            'current_technical_potential' => $player->current_technical_potential,
            'current_mental_potential' => $player->current_mental_potential,
            'current_physical_potential' => $player->current_physical_potential,
        ];

        foreach (self::PLAYER_ATTRIBUTE_COLUMNS as $column) {
            $row[$column] = $player->{$column};
        }

        foreach (PlayerFields::GOALKEEPING_FIELDS as $field) {
            $row[$field] = $player->{$field};
        }

        return $row;
    }

    public function calculatePlayerValueWithinClub(Player $player): int
    {
        $currentPotentialValue = $this->valuationByAttribute($player->potential);

        $maxPotentialValue = $this->valuationByAttribute($player->max_potential);
        $marketingRankValue = $this->valuationByAttribute($player->marketing_rank);

        $amount = $currentPotentialValue > $maxPotentialValue ? $currentPotentialValue :
            $maxPotentialValue - (($maxPotentialValue - $currentPotentialValue) / 2);

        $amount = $marketingRankValue > $amount ? $amount + (($maxPotentialValue - $amount) / 2) :
            $amount - (($amount - $maxPotentialValue) / 2);

        return DisplayHelpers::roundAmounts($amount);
    }

    private function valuationByAttribute(int $attributeValue)
    {
        $attributeValue = min($attributeValue, 200);
        for ($k = 0.1, $i = 10; $i <= 200; $i += 10, $k += 0.06) {
            if ($attributeValue > $i) {
                continue;
            }

            $value = 180 * round(pow($attributeValue, $k), 2) * 1000;
            break;
        }

        return $value;
    }

    public function activePlayers(int $instanceId): LazyCollection
    {
        return Player::query()
            ->forInstance($instanceId)
            ->active()
            ->with('person:id,dob')
            ->lazyById(200);
    }

    public function playersEligibleForRetirement(int $instanceId, string $date): LazyCollection
    {
        return Player::query()
            ->forInstance($instanceId)
            ->where('is_retired', false)
            ->whereHas('person', function ($query) use ($date): void {
                $query->whereNotNull('dob')->whereDate('dob', '<=', $date);
            })
            ->with('person')
            ->lazyById(200);
    }

    public function retirePlayer(int $playerId): bool
    {
        return DB::transaction(function () use ($playerId): bool {
            $player = Player::query()->lockForUpdate()->findOrFail($playerId);

            if ($player->is_retired) {
                return false;
            }

            $contractId = $player->contract_id;

            $player->forceFill([
                'is_retired' => true,
                'club_id' => null,
                'loan_club_id' => null,
                'loan_start' => null,
                'loan_end' => null,
                'contract_id' => null,
            ])->save();

            if ($contractId !== null) {
                DB::table('players_contracts')->where('id', $contractId)->delete();
            }

            DB::table('players_progress')->where('player_id', $player->id)->delete();
            DB::table('transfer_list')->where('player_id', $player->id)->delete();
            $this->voidOngoingTransfersForPlayer($player->id);

            return true;
        });
    }

    private function voidOngoingTransfersForPlayer(int $playerId): void
    {
        $ongoingTransferIds = DB::table('transfers')
            ->where('player_id', $playerId)
            ->where(function ($query): void {
                $query
                    ->whereNull('transfer_status')
                    ->orWhereNotIn('transfer_status', [
                        TransferStatusTypes::TRANSFER_COMPLETED->value,
                        TransferStatusTypes::TRANSFER_FAILED->value,
                    ]);
            })
            ->pluck('id');

        if ($ongoingTransferIds->isEmpty()) {
            return;
        }

        DB::table('transfer_contract_offers')->whereIn('transfer_id', $ongoingTransferIds)->delete();
        DB::table('transfer_financial_details')->whereIn('transfer_id', $ongoingTransferIds)->delete();
        DB::table('transfer_payments')->whereIn('transfer_id', $ongoingTransferIds)->delete();
        DB::table('transfers')->whereIn('id', $ongoingTransferIds)->delete();
    }

    public function contractBasedOnPotential(Player $player): array
    {
        return $this->playerDataSource->contractBasedOnPotential($player);
    }
}
