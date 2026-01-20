<?php

namespace App\Repositories;

use App\Models\Club;
use App\Models\Instance;
use App\Models\Player;
use App\Services\TransferService\TransferTypes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TransferSearchRepository extends CoreRepository
{
    public function playersByAttributes(Club $club, array $searchableAttribute)
    {
        $instance = Instance::find($this->instanceId);

        return DB::table('players AS p')
            ->select('p.*')
            ->leftJoin('transfers AS t', 't.player_id', '=', 'p.id' )
            ->where(function ($query) use ($searchableAttribute){
                foreach ($searchableAttribute as $attribute => $value) {
                    $query->where($attribute, '>=', $value);
                }
            })
            ->where('p.instance_id', $this->instanceId)
            ->where('p.club_id', '<>', $club->id)
            ->where('p.position', '=','CB')
            ->where(function ($query) use($instance){
                // club shouldn't be applying for a player that was already offered to by them
                $query->where('t.offer_date', '>', 't.offer_date > DATE_SUB(' . $instance->instance_date . '",INTERVAL 2 YEAR')
                    ->orWhereNull('t.offer_date');
            })
            ->get();
    }

    public function findPlayersByPositionForClub(Club $club, string $position): Collection
    {
        $instance = Instance::find($club->instance_id);

        $collection = DB::table('players AS p')
            ->select('p.*')
            ->leftJoin('transfers AS t', function ($query) use ($instance, $club) {
                $query->on('t.player_id', '=', 'p.id')
                    ->whereRaw("
                        `t`.`offer_date` > DATE_SUB('" . $instance->instance_date . "', INTERVAL 2 year)
                        AND p.club_id <> " . $club->id . "
                    ");
            })
            ->whereNull('t.player_id')
            ->where('p.instance_id', $instance->id)
            ->where('p.club_id', '<>', $club->id)
            ->where('p.position', '=', $position)
            ->where('p.potential', '>=', $club->rank * 10 - 20)
            ->orderBy('p.potential', 'desc')
            ->get();

        return Player::hydrate($collection->toArray());
    }

    public function findLuxuryPlayersForPosition(Club $buyingClub, string $position, int $clubBudget): Player|null
    {
        $highestPotentialPlayer = Player::where('position', $position)
            ->where('club_id', $buyingClub->id)
            ->where('instance_id', $this->instanceId)
            ->orderBy('potential', 'DESC')
            ->first();

        if (!$highestPotentialPlayer) {
            return null;
        }

        $players = Player::where('position', $position)
            ->where('potential', '>', $highestPotentialPlayer->potential)
            ->where('club_id', '<>', $buyingClub->id)
            ->where('value', '<=', $clubBudget)
            ->get();

        return $players->first();
    }

    public function findListedPlayer(
        Club $buyingClub,
        int $transferType,
        string $position,
        ?int $clubBudget = 0
    ): Player|null
    {
        $highestPotentialPlayer = Player::where('position', $position)
            ->where('club_id', $buyingClub->id)
            ->orderBy('potential', 'DESC')
            ->first();

        $players = DB::table('players AS p')
            ->select('p.*')
            ->join('transfer_list AS tl', 'tl.player_id', '=', 'p.id')
            ->where('p.club_id', '<>', $buyingClub->id)
            ->where('tl.transfer_type', '=', $transferType)
            ->when($transferType == TransferTypes::PERMANENT_TRANSFER, function ($query) use ($highestPotentialPlayer) {
                return $query->where('p.potential', '>', $highestPotentialPlayer ? $highestPotentialPlayer->potential : 0);
            })
            ->when($transferType == TransferTypes::PERMANENT_TRANSFER, function ($query) use ($clubBudget){
                return $query->where('p.value', '<=', $clubBudget);
            })
            ->orderBy('p.potential', 'desc')
            ->get();

        return Player::hydrate($players->toArray())->first();
    }

    public function findListedLoanPlayers(
        Club $club,
        string $position,
    ) :?Player
    {
        // find average potential for players within club
        // loan offer should be fore more than that
        $averagePlayerPotentialForClub = DB::table('players AS p')
            ->where('p.club_id', '=', $club->id)->pluck('potential')->avg();

        $listedPlayers = DB::table('players AS p')
            ->select('p.*')
            ->join('transfer_list AS tl', 'tl.player_id', '=', 'p.id')
            ->where('tl.transfer_type', '=', TransferTypes::LOAN_TRANSFER)
            ->where('p.club_id', '<>', $club->id)
            ->where('p.position', '=', $position)
            ->where('p.potential', '>=', $averagePlayerPotentialForClub)
            ->get();

        return Player::hydrate($listedPlayers->toArray())->first();
    }

    public function findFreePlayerForPosition(Club $club, string $position, bool $luxury = false)
    {
        $highestPotentialPlayer = null;

        if ($luxury) {
            $highestPotentialPlayer = Player::where('position', $position)
                ->where('club_id', $club->id)
                ->orderBy('potential', 'DESC')
                ->first();
        }

        $players = DB::table('players AS p')
            ->select('p.*')
            ->whereNull('p.contract_id')
            ->where('p.potential', '>=',  $club->rank * 10 - 20)
            ->when($luxury, function ($query) use ($highestPotentialPlayer) {
                $query->where('p.potential', '>', $highestPotentialPlayer->potential);
            })
            ->get();

        return Player::hydrate($players->toArray())->first();
    }

    public function findPlayersWithUnprotectedContracts(
        Club $club,
        string $position,
        int $clubBudget
    ): ?Player
    {
        $instance = Instance::find($this->instanceId);

        $player = DB::table('players AS p')
            ->select('p.*')
            ->join('players_contracts AS pc', function ($query) use ($instance) {
                $query->on('pc.id', '=', 'p.contract_id')
                      ->whereRaw("
                        `pc`.`contract_end` BETWEEN DATE('" . $instance->instance_date . "')
                        AND DATE_ADD('" . $instance->instance_date . "', INTERVAL 6 MONTH)
                    ");
            })
            ->where('p.club_id', '<>', $club->id)
            ->where('p.potential', '>=', $club->rank * 10 - 20)
            ->where('p.position', '=', $position)
            ->orderBy('p.potential', 'desc')
            ->get();

        return Player::hydrate($player->toArray())->first();
    }
}
