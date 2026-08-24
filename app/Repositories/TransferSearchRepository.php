<?php

namespace App\Repositories;

use App\Models\Club;
use App\Models\Player;
use App\Services\PersonService\PersonConfig\Player\PlayerFields;
use App\Services\TransferService\TransferTypes;
use App\Support\GameContext;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TransferSearchRepository
{
    public function __construct(
        private readonly GameContext $gameContext,
    ) {}
    public function findPlayersByAttributes(Club $club, array $searchableAttributes): Collection
    {
        $searchableAttributes = $this->validatedSearchableAttributes($searchableAttributes);
        $instanceId = $this->gameContext->instanceId();
        $recentOfferCutoff = Carbon::parse($this->gameContext->instanceDate())->subYears(2);

        return Player::query()->from('players AS p')
            ->select('p.*')
            ->where('p.is_retired', false)
            ->leftJoin('transfers AS t', function ($query) use ($instanceId, $club, $recentOfferCutoff) {
                $query->on('t.player_id', '=', 'p.id')
                    ->where('t.instance_id', '=', $instanceId)
                    ->where('t.source_club_id', '=', $club->id)
                    ->where('t.offer_date', '>', $recentOfferCutoff);
            })
            ->where(function ($query) use ($searchableAttributes) {
                foreach ($searchableAttributes as $attribute => $value) {
                    $query->where("p.{$attribute}", '>=', $value);
                }
            })
            ->where('p.instance_id', $instanceId)
            ->where('p.club_id', '<>', $club->id)
            ->whereNull('t.player_id')
            ->get();
    }

    public function findPlayersByPositionForClub(Club $club, string $position): Collection
    {
        $instanceId = $this->gameContext->instanceId();
        $recentOfferCutoff = Carbon::parse($this->gameContext->instanceDate())->subYears(2);

        $collection = Player::query()->from('players AS p')
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
            ->orderByDesc('p.potential')
            ->orderBy('p.id')
            ->get();

        return $collection;
    }

    public function findLuxuryPlayerForPosition(Club $buyingClub, string $position, int $clubBudget): ?Player
    {
        $instanceId = $this->gameContext->instanceId();

        $highestPotentialPlayer = Player::where('position', $position)
            ->where('is_retired', false)
            ->where('club_id', $buyingClub->id)
            ->where('instance_id', $instanceId)
            ->orderByDesc('potential')
            ->orderBy('id')
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
            ->orderByDesc('potential')
            ->orderBy('id')
            ->get();

        return $players->first();
    }

    public function findListedPlayer(
        Club $buyingClub,
        int $transferType,
        string $position,
        int $clubBudget = 0
    ): ?Player {
        $instanceId = $this->gameContext->instanceId();

        $highestPotentialPlayer = Player::where('position', $position)
            ->where('is_retired', false)
            ->where('instance_id', $instanceId)
            ->where('club_id', $buyingClub->id)
            ->orderByDesc('potential')
            ->orderBy('id')
            ->first();

        $players = Player::query()->from('players AS p')
            ->select('p.*')
            ->where('p.is_retired', false)
            ->where('p.instance_id', $instanceId)
            ->join('transfer_list AS tl', 'tl.player_id', '=', 'p.id')
            ->where('p.club_id', '<>', $buyingClub->id)
            ->where('p.position', $position)
            ->where('tl.transfer_type', '=', $transferType)
            ->when($transferType === TransferTypes::PERMANENT_TRANSFER, function ($query) use ($highestPotentialPlayer) {
                return $query->where('p.potential', '>', $highestPotentialPlayer ? $highestPotentialPlayer->potential : 0);
            })
            ->when($transferType === TransferTypes::PERMANENT_TRANSFER, function ($query) use ($clubBudget) {
                return $query->where('p.value', '<=', $clubBudget);
            })
            ->orderByDesc('p.potential')
            ->orderBy('p.id')
            ->get();

        return $players->first();
    }

    public function findListedLoanPlayer(
        Club $club,
        string $position,
    ): ?Player {
        $instanceId = $this->gameContext->instanceId();

        // find average potential for players within club
        // loan offer should be fore more than that
        $averagePlayerPotentialForClub = Player::query()->from('players AS p')
            ->where('p.instance_id', $instanceId)
            ->where('p.club_id', '=', $club->id)->pluck('potential')->avg();

        $listedPlayers = Player::query()->from('players AS p')
            ->select('p.*')
            ->where('p.is_retired', false)
            ->where('p.instance_id', $instanceId)
            ->join('transfer_list AS tl', 'tl.player_id', '=', 'p.id')
            ->where('tl.transfer_type', '=', TransferTypes::LOAN_TRANSFER)
            ->where('p.club_id', '<>', $club->id)
            ->where('p.position', '=', $position)
            ->where('p.potential', '>=', $averagePlayerPotentialForClub)
            ->orderByDesc('p.potential')
            ->orderBy('p.id')
            ->get();

        return $listedPlayers->first();
    }

    public function findFreePlayerForPosition(Club $club, string $position, bool $luxury = false): ?Player
    {
        $instanceId = $this->gameContext->instanceId();
        $highestPotentialPlayer = null;

        if ($luxury) {
            $highestPotentialPlayer = Player::where('position', $position)
                ->where('is_retired', false)
                ->where('instance_id', $instanceId)
                ->where('club_id', $club->id)
                ->orderByDesc('potential')
                ->orderBy('id')
                ->first();
        }

        $players = Player::query()->from('players AS p')
            ->select('p.*')
            ->where('p.is_retired', false)
            ->where('p.instance_id', $instanceId)
            ->whereNull('p.contract_id')
            ->where('p.position', $position)
            ->where('p.potential', '>=', $club->rank * 10 - 20)
            ->when($luxury && $highestPotentialPlayer, function ($query) use ($highestPotentialPlayer) {
                $query->where('p.potential', '>', $highestPotentialPlayer->potential);
            })
            ->orderByDesc('p.potential')
            ->orderBy('p.id')
            ->get();

        return $players->first();
    }

    public function findPlayerWithUnprotectedContract(
        Club $club,
        string $position,
        int $clubBudget
    ): ?Player {
        $instanceId = $this->gameContext->instanceId();
        $instanceDate = Carbon::parse($this->gameContext->instanceDate());
        $contractStart = $instanceDate->toDateString();
        $contractEnd = $instanceDate->copy()->addMonths(6)->toDateString();

        $player = Player::query()->from('players AS p')
            ->select('p.*')
            ->where('p.is_retired', false)
            ->where('p.instance_id', $instanceId)
            ->join('players_contracts AS pc', function ($query) use ($contractStart, $contractEnd) {
                $query->on('pc.id', '=', 'p.contract_id')
                    ->whereBetween('pc.contract_end', [$contractStart, $contractEnd]);
            })
            ->where('p.club_id', '<>', $club->id)
            ->where('p.potential', '>=', $club->rank * 10 - 20)
            ->where('p.position', '=', $position)
            ->orderByDesc('p.potential')
            ->orderBy('p.id')
            ->get();

        return $player->first();
    }

    private function validatedSearchableAttributes(array $searchableAttributes): array
    {
        $allowedAttributes = array_merge(
            PlayerFields::TECHNICAL_FIELDS,
            PlayerFields::MENTAL_FIELDS,
            PlayerFields::PHYSICAL_FIELDS,
            PlayerFields::PERSON_ATTRIBUTE_CATEGORIES,
        );

        foreach (array_keys($searchableAttributes) as $attribute) {
            if (! in_array($attribute, $allowedAttributes, true)) {
                throw new \InvalidArgumentException("Unsupported player search attribute: {$attribute}");
            }
        }

        return $searchableAttributes;
    }

}
