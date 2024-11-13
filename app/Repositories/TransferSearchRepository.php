<?php

namespace App\Repositories;

use App\Models\Club;
use App\Models\Instance;
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

        return DB::table('players AS p')
            ->select('p.*')
            ->leftJoin('transfers AS t', function ($query) use ($instance) {
                $query->on('t.player_id', '=', 'p.id')
                    ->whereRaw("`t`.`offer_date` > DATE_SUB('" . $instance->instance_date . "', INTERVAL 2 year)");
            })
            ->whereNull('t.player_id')
            ->where('p.instance_id', $instance->id)
            ->where('p.club_id', '<>', $club->id)
            ->where('p.position', '=', $position)
            ->where('p.potential', '>=', $club->rank * 10 - 20)
            ->orderBy('p.potential', 'desc')
            ->get();
    }

    public function filterTopPlayerByPosition(string $position)
    {

    }
}
