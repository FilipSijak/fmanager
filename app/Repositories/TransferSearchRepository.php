<?php

namespace App\Repositories;

use App\Models\Club;
use App\Models\Instance;
use App\Models\Player;
use App\Models\TransferList;
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

    public function findLuxuryPlayersForPosition(Club $club, string $position)
    {
        $highestPotentialPlayer = Player::where('position', $position)
                                      ->where('club_id', $club->id)
                                      ->orderBy('potential', 'DESC')
                                      ->first();

        return Player::where('position', $position)
                     ->where('potential', '>', $highestPotentialPlayer->potential)
                     ->first();
    }

    public function getHighestListedPlayer(Club $club, int $transferType, string $position): Player|null
    {
        $highestPotentialPlayer = Player::where('position', $position)
                                        ->where('club_id', $club->id)
                                        ->orderBy('potential', 'DESC')
                                        ->first();

        $player =  DB::table('players AS p')
            ->select('p.*')
            ->join('transfer_list AS tl', 'tl.player_id', '=', 'p.id')
            ->where('tl.transfer_type', '=', $transferType)
            ->where('p.potential', '>', $highestPotentialPlayer ? $highestPotentialPlayer->potential : 0)
            ->where('p.club_id', '<>', $club->id)
            ->orderBy('p.potential', 'desc')
            ->get();

        return Player::hydrate($player->toArray())->first();
    }
}
