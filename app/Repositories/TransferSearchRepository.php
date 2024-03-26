<?php

namespace App\Repositories;

use App\Models\Instance;
use Illuminate\Support\Facades\DB;

class TransferSearchRepository extends CoreRepository
{
    public function playersByAttributes(int $clubId, array $searchableAttribute)
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
            ->where('p.club_id', '<>', $clubId)
            ->where('p.position', '=','CB')
            ->where(function ($query) use($instance){
                $query->where('t.offer_date', '>', 't.offer_date > DATE_SUB(' . $instance->instance_date . '",INTERVAL 2 YEAR')
                    ->orWhereNull('t.offer_date');
            })
            ->get();
    }
}

