<?php

namespace App\Repositories;

use App\Models\Club;
use App\Models\Instance;
use App\Models\Player;
use App\Services\TransferService\TransferTypes;
use App\Support\GameContext;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TransferSearchRepository extends CoreRepository
{
    public function playersByAttributes(Club $club, array $searchableAttribute)
    {
        $instanceId = $this->instanceId();
        $recentOfferCutoff = Carbon::parse($this->instanceDate($instanceId))->subYears(2);

        return DB::table('players AS p')
            ->select('p.*')
            ->where('p.is_retired', false)
            ->leftJoin('transfers AS t', function ($query) use ($instanceId, $club, $recentOfferCutoff) {
                $query->on('t.player_id', '=', 'p.id')
                    ->where('t.instance_id', '=', $instanceId)
                    ->where('t.source_club_id', '=', $club->id)
                    ->where('t.offer_date', '>', $recentOfferCutoff);
            })
            ->where(function ($query) use ($searchableAttribute) {
                foreach ($searchableAttribute as $attribute => $value) {
                    $query->where($attribute, '>=', $value);
                }
            })
            ->where('p.instance_id', $instanceId)
            ->where('p.club_id', '<>', $club->id)
            ->whereNull('t.player_id')
            ->get();
    }

    public function findPlayersByPositionForClub(Club $club, string $position): Collection
    {
        $instanceId = $this->instanceId();
        $recentOfferCutoff = Carbon::parse($this->instanceDate($instanceId))->subYears(2);

        $collection = DB::table('players AS p')
            ->select('p.*')
            ->where('p.is_retired', false)
            ->leftJoin('transfers AS t', function ($query) use ($instanceId, $club, $recentOfferCutoff) {
                $query->on('t.player_id', '=', 'p.id')
                    ->where('t.instance_id', '=', $instanceId)
                    ->where('t.source_club_id', '=', $club->id)
                    ->where('t.offer_date', '>', $recentOfferCutoff);
            })
            ->whereNull('t.player_id')
            ->where('p.instance_id', $instanceId)
            ->where('p.club_id', '<>', $club->id)
            ->where('p.position', '=', $position)
            ->where('p.potential', '>=', $club->rank * 10 - 20)
            ->orderBy('p.potential', 'desc')
            ->get();

        return Player::hydrate($collection->toArray());
    }

    public function findLuxuryPlayersForPosition(Club $buyingClub, string $position, int $clubBudget): ?Player
    {
        $instanceId = $this->instanceId();

        $highestPotentialPlayer = Player::where('position', $position)
            ->where('is_retired', false)
            ->where('club_id', $buyingClub->id)
            ->where('instance_id', $instanceId)
            ->orderBy('potential', 'DESC')
            ->first();

        if (! $highestPotentialPlayer) {
            return null;
        }

        $players = Player::where('position', $position)
            ->where('is_retired', false)
            ->where('instance_id', $instanceId)
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
    ): ?Player {
        $instanceId = $this->instanceId();

        $highestPotentialPlayer = Player::where('position', $position)
            ->where('is_retired', false)
            ->where('instance_id', $instanceId)
            ->where('club_id', $buyingClub->id)
            ->orderBy('potential', 'DESC')
            ->first();

        $players = DB::table('players AS p')
            ->select('p.*')
            ->where('p.is_retired', false)
            ->where('p.instance_id', $instanceId)
            ->join('transfer_list AS tl', 'tl.player_id', '=', 'p.id')
            ->where('p.club_id', '<>', $buyingClub->id)
            ->where('tl.transfer_type', '=', $transferType)
            ->when($transferType == TransferTypes::PERMANENT_TRANSFER, function ($query) use ($highestPotentialPlayer) {
                return $query->where('p.potential', '>', $highestPotentialPlayer ? $highestPotentialPlayer->potential : 0);
            })
            ->when($transferType == TransferTypes::PERMANENT_TRANSFER, function ($query) use ($clubBudget) {
                return $query->where('p.value', '<=', $clubBudget);
            })
            ->orderBy('p.potential', 'desc')
            ->get();

        return Player::hydrate($players->toArray())->first();
    }

    public function findListedLoanPlayers(
        Club $club,
        string $position,
    ): ?Player {
        $instanceId = $this->instanceId();

        // find average potential for players within club
        // loan offer should be fore more than that
        $averagePlayerPotentialForClub = DB::table('players AS p')
            ->where('p.instance_id', $instanceId)
            ->where('p.club_id', '=', $club->id)->pluck('potential')->avg();

        $listedPlayers = DB::table('players AS p')
            ->select('p.*')
            ->where('p.is_retired', false)
            ->where('p.instance_id', $instanceId)
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
        $instanceId = $this->instanceId();
        $highestPotentialPlayer = null;

        if ($luxury) {
            $highestPotentialPlayer = Player::where('position', $position)
                ->where('is_retired', false)
                ->where('instance_id', $instanceId)
                ->where('club_id', $club->id)
                ->orderBy('potential', 'DESC')
                ->first();
        }

        $players = DB::table('players AS p')
            ->select('p.*')
            ->where('p.is_retired', false)
            ->where('p.instance_id', $instanceId)
            ->whereNull('p.contract_id')
            ->where('p.potential', '>=', $club->rank * 10 - 20)
            ->when($luxury && $highestPotentialPlayer, function ($query) use ($highestPotentialPlayer) {
                $query->where('p.potential', '>', $highestPotentialPlayer->potential);
            })
            ->get();

        return Player::hydrate($players->toArray())->first();
    }

    public function findPlayersWithUnprotectedContracts(
        Club $club,
        string $position,
        int $clubBudget
    ): ?Player {
        $instanceId = $this->instanceId();
        $instanceDate = $this->instanceDate($instanceId);

        $player = DB::table('players AS p')
            ->select('p.*')
            ->where('p.is_retired', false)
            ->where('p.instance_id', $instanceId)
            ->join('players_contracts AS pc', function ($query) use ($instanceDate) {
                $query->on('pc.id', '=', 'p.contract_id')
                    ->whereRaw("
                        `pc`.`contract_end` BETWEEN DATE('".$instanceDate."')
                        AND DATE_ADD('".$instanceDate."', INTERVAL 6 MONTH)
                    ");
            })
            ->where('p.club_id', '<>', $club->id)
            ->where('p.potential', '>=', $club->rank * 10 - 20)
            ->where('p.position', '=', $position)
            ->orderBy('p.potential', 'desc')
            ->get();

        return Player::hydrate($player->toArray())->first();
    }

    private function instanceDate(int $instanceId): string
    {
        $gameContext = app(GameContext::class);

        if ($gameContext->hasInstanceDate()) {
            return $gameContext->instanceDate();
        }

        $instanceDate = Instance::query()->whereKey($instanceId)->value('instance_date');

        if ($instanceDate === null) {
            throw new \LogicException("Active game instance {$instanceId} does not exist.");
        }

        $gameContext->setInstanceDate($instanceDate);

        return $instanceDate;
    }
}
