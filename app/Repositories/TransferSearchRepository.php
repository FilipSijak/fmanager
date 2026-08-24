<?php

namespace App\Repositories;

use App\Models\Club;
use App\Models\Player;
use App\Services\SearchService\PlayerSearchQuery;
use App\Services\TransferService\TransferSearchPolicies\TransferSearchCriteria;
use App\Services\TransferService\TransferType;
use App\Support\GameContext;
use Illuminate\Database\Eloquent\Collection;

class TransferSearchRepository
{
    public function __construct(
        private readonly GameContext $gameContext,
        private readonly TransferSearchCriteria $criteria,
        private readonly PlayerSearchQuery $playerSearchQuery,
    ) {}

    /**  Collection<int, Player> */
    public function findPlayersByAttributes(Club $club, array $searchableAttributes): Collection
    {
        $instanceId = $this->gameContext->instanceId();
        $recentOfferCutoff = $this->criteria->recentOfferCutoff($this->gameContext->instanceDate());

        return $this->playerSearchQuery->activeAliasedMatchingAttributes($searchableAttributes)
            ->leftJoin('transfers AS t', function ($query) use ($instanceId, $club, $recentOfferCutoff) {
                $query->on('t.player_id', '=', 'p.id')
                    ->where('t.instance_id', '=', $instanceId)
                    ->where('t.source_club_id', '=', $club->id)
                    ->where('t.offer_date', '>', $recentOfferCutoff);
            })
            ->where('p.club_id', '<>', $club->id)
            ->whereNull('t.player_id')
            ->get();
    }

    /**  Collection<int, Player> */
    public function findTransferTargetsByPosition(Club $club, string $position): Collection
    {
        $instanceId = $this->gameContext->instanceId();
        $recentOfferCutoff = $this->criteria->recentOfferCutoff($this->gameContext->instanceDate());

        $collection = $this->playerSearchQuery->activeAliased()
            ->leftJoin('transfers AS t', function ($query) use ($instanceId, $club, $recentOfferCutoff) {
                $query->on('t.player_id', '=', 'p.id')
                    ->where('t.instance_id', '=', $instanceId)
                    ->where('t.source_club_id', '=', $club->id)
                    ->where('t.offer_date', '>', $recentOfferCutoff);
            })
            ->whereNull('t.player_id')
            ->where('p.club_id', '<>', $club->id)
            ->where('p.position', '=', $position)
            ->where('p.potential', '>=', $this->criteria->minimumPotentialFor($club))
            ->orderByDesc('p.potential')
            ->orderBy('p.id')
            ->get();

        return $collection;
    }

    public function findUpgradeTargetByPosition(Club $buyingClub, string $position, int $clubBudget): ?Player
    {
        $highestPotential = $this->playerSearchQuery->active()
            ->where('position', $position)
            ->where('club_id', $buyingClub->id)
            ->max('potential');

        if ($highestPotential === null) {
            return null;
        }

        return $this->playerSearchQuery->active()
            ->where('position', $position)
            ->where('potential', '>=', $this->criteria->minimumUpgradePotential((int) $highestPotential))
            ->where('club_id', '<>', $buyingClub->id)
            ->where('value', '<=', $clubBudget)
            ->orderByDesc('potential')
            ->orderBy('id')
            ->first();
    }

    public function findListedPlayer(
        Club $buyingClub,
        TransferType $transferType,
        string $position,
        int $clubBudget = 0
    ): ?Player {
        $highestPotential = (int) ($this->playerSearchQuery->active()
            ->where('position', $position)
            ->where('club_id', $buyingClub->id)
            ->max('potential') ?? 0);

        return $this->playerSearchQuery->activeAliased()
            ->join('transfer_list AS tl', 'tl.player_id', '=', 'p.id')
            ->where('p.club_id', '<>', $buyingClub->id)
            ->where('p.position', $position)
            ->where('tl.transfer_type', $transferType->value)
            ->when($this->criteria->requiresUpgrade($transferType), function ($query) use ($highestPotential) {
                return $query->where(
                    'p.potential',
                    '>=',
                    $this->criteria->minimumUpgradePotential($highestPotential)
                );
            })
            ->when($this->criteria->requiresUpgrade($transferType), function ($query) use ($clubBudget) {
                return $query->where('p.value', '<=', $clubBudget);
            })
            ->orderByDesc('p.potential')
            ->orderBy('p.id')
            ->first();
    }

    public function findListedLoanPlayer(
        Club $club,
        string $position,
    ): ?Player {
        $averagePlayerPotentialForClub = $this->playerSearchQuery->active()
            ->where('club_id', $club->id)
            ->avg('potential');
        $minimumPotential = $this->criteria->minimumLoanPotential(
            $averagePlayerPotentialForClub === null ? null : (float) $averagePlayerPotentialForClub
        );

        return $this->playerSearchQuery->activeAliased()
            ->join('transfer_list AS tl', 'tl.player_id', '=', 'p.id')
            ->where('tl.transfer_type', '=', TransferType::LOAN_TRANSFER->value)
            ->where('p.club_id', '<>', $club->id)
            ->where('p.position', '=', $position)
            ->where('p.potential', '>=', $minimumPotential)
            ->orderByDesc('p.potential')
            ->orderBy('p.id')
            ->first();
    }

    public function findFreePlayerForPosition(Club $club, string $position, bool $luxury = false): ?Player
    {
        $highestPotentialPlayer = null;

        if ($luxury) {
            $highestPotentialPlayer = $this->playerSearchQuery->active()
                ->where('position', $position)
                ->where('club_id', $club->id)
                ->orderByDesc('potential')
                ->orderBy('id')
                ->first();
        }

        return $this->playerSearchQuery->activeAliased()
            ->whereNull('p.contract_id')
            ->whereNull('p.club_id')
            ->where('p.position', $position)
            ->where('p.potential', '>=', $this->criteria->minimumPotentialFor($club))
            ->when($luxury && $highestPotentialPlayer, function ($query) use ($highestPotentialPlayer) {
                $query->where(
                    'p.potential',
                    '>=',
                    $this->criteria->minimumUpgradePotential($highestPotentialPlayer->potential)
                );
            })
            ->orderByDesc('p.potential')
            ->orderBy('p.id')
            ->first();
    }

    public function findExpiringContractTarget(
        Club $club,
        string $position,
        int $clubBudget
    ): ?Player {
        [$contractStart, $contractEnd] = $this->criteria
            ->expiringContractWindow($this->gameContext->instanceDate());

        return $this->playerSearchQuery->activeAliased()
            ->join('players_contracts AS pc', function ($query) use ($contractStart, $contractEnd) {
                $query->on('pc.id', '=', 'p.contract_id')
                    ->whereBetween('pc.contract_end', [$contractStart, $contractEnd]);
            })
            ->where('p.club_id', '<>', $club->id)
            ->where('p.potential', '>=', $this->criteria->minimumPotentialFor($club))
            ->where('p.position', '=', $position)
            ->where('p.value', '<=', $clubBudget)
            ->orderByDesc('p.potential')
            ->orderBy('p.id')
            ->first();
    }
}
