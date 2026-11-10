<?php

namespace App\Repositories;

use App\Models\Account;
use App\Models\Club;
use App\Models\Game;
use App\Models\GamePlayerStat;
use App\Models\Instance;
use App\Models\Player;
use App\Models\PlayerContract;
use App\Models\StaffContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class ContractRepository
{
    /** @param Collection<int, Player> $players */
    public function loadPlayerPeople(Collection $players): void
    {
        (new EloquentCollection($players->all()))->loadMissing('person');
    }

    /** @param array<string, mixed> $data */
    public function createPlayerContract(array $data): PlayerContract
    {
        $contract = new PlayerContract;
        $contract->forceFill($data)->save();

        return $contract;
    }

    public function currentPlayerContract(Player $player): ?PlayerContract
    {
        return $player->contract()->lockForUpdate()->first();
    }

    /** @param array<string, mixed> $data */
    public function updatePlayerContract(PlayerContract $contract, array $data): void
    {
        $contract->forceFill($data)->save();
    }

    /** @param array<string, mixed> $data */
    public function createStaffContract(array $data): StaffContract
    {
        return StaffContract::query()->create($data);
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

    /** @return array{games: int, appearances: list<array<string, mixed>>} */
    public function recentContractPerformance(Player $player, ?int $seasonId, string $date): array
    {
        $clubId = (int) ($player->loan_club_id ?? $player->club_id);
        $games = Game::query()->forInstance((int) $player->instance_id)->forClub($clubId)
            ->where('season_id', $seasonId)->where('status', Game::STATUS_COMPLETED)
            ->whereDate('match_start', '<=', $date)->orderByDesc('match_start')->orderByDesc('id')
            ->limit(10)->pluck('id');
        $appearances = $games->isEmpty() ? [] : GamePlayerStat::query()->forPlayer($player)
            ->completedAppearances($seasonId, $date)->whereIn('game_player_stats.game_id', $games)
            ->where('game_player_stats.club_id', $clubId)->select('game_player_stats.*')->get()->toArray();

        return ['games' => $games->count(), 'appearances' => $appearances];
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
