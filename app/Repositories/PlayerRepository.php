<?php

namespace App\Repositories;

use App\Helpers\DisplayHelpers;
use App\Models\Club;
use App\Models\Player;
use App\Repositories\Interfaces\IPlayerRepository;
use App\Services\PersonService\DataLayer\PlayerDataSource;
use App\Services\PersonService\GeneratePeople\PlayerPosition;
use App\Services\PersonService\PersonConfig\Player\PlayerPositionConfig;
use App\Services\TransferService\TransferStatusTypes;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use RuntimeException;

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
            $positionList = $playerPositionGenerator->getInitialPositionsBasedOnAttributes($attributes);
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
        ];

        foreach (self::PLAYER_ATTRIBUTE_COLUMNS as $column) {
            $row[$column] = $player->{$column};
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
