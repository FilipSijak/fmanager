<?php

namespace App\Repositories;

use App\Models\Account;
use App\Models\Club;
use App\Models\Instance;
use App\Models\Player;
use App\Models\PlayerContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class ContractRepository
{
    /** @param array<string, mixed> $data */
    public function createPlayerContract(array $data): PlayerContract
    {
        $contract = new PlayerContract;
        $contract->forceFill($data)->save();

        return $contract;
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

    public function contractAccount(Club $club): Account
    {
        return Account::query()->where('club_id', $club->id)->lockForUpdate()->firstOrFail();
    }

    public function annualContractWages(Club $club, int $excludingPlayerId, string $date): int
    {
        return (int) Player::query()->forClub($club)->withCurrentContractOn($date)
            ->where('players.id', '!=', $excludingPlayerId)
            ->join('players_contracts', 'players_contracts.id', '=', 'players.contract_id')->sum('players_contracts.salary') * 52;
    }

    /** @param array<string, int> $terms */
    public function applyContractRenewal(Player $player, Account $account, array $terms, string $date, string $endsAt, int $months): void
    {
        $this->recordRenewalOffer($player, $terms, $date, $endsAt, $months, 'accepted');
        $player->contract->forceFill($terms + ['contract_start' => $date, 'contract_end' => $endsAt])->save();
        $account->balance -= $terms['signing_fee'];
        $account->future_balance -= $terms['signing_fee'];
        $account->transfer_budget -= $terms['signing_fee'];
        $account->save();
    }

    /** @param array<string, int> $terms */
    public function recordRenewalOffer(Player $player, array $terms, string $date, string $endsAt, int $months, string $status): void
    {
        DB::table('player_contract_renewals')->insert([
            'instance_id' => $player->instance_id, 'club_id' => $player->club_id, 'player_id' => $player->id,
            'contract_id' => $player->contract_id, 'status' => $status, 'months' => $months,
            'previous_contract_end' => $player->contract->contract_end, 'contract_end' => $endsAt, 'offered_on' => $date,
            'terms' => json_encode($terms, JSON_THROW_ON_ERROR),
        ]);
    }

    /** @return LazyCollection<int, Player> */
    public function contractsEndingBy(int $instanceId, string $date): LazyCollection
    {
        return Player::query()->forInstance($instanceId)->active()->whereNotNull('club_id')
            ->whereHas('club', fn (Builder $club): Builder => $club->forInstance($instanceId))
            ->withContractEndingBy($date)->with(['person', 'contract', 'club.account'])
            ->lockForUpdate()->lazyById(200);
    }

    public function markContractReminder(Player $player): void
    {
        $player->contract->forceFill(['renewal_reminded_for' => $player->contract->contract_end])->save();
    }

    public function deletePlayerContract(PlayerContract $contract): void
    {
        $contract->delete();
    }
}
