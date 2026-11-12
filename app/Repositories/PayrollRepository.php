<?php

namespace App\Repositories;

use App\Models\Account;
use App\Models\Club;
use App\Models\Player;
use App\Models\StaffCoaching;
use App\Models\StaffPhysio;
use App\Models\StaffScout;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class PayrollRepository
{
    public function transaction(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }

    /** @return LazyCollection<int, Club> */
    public function clubs(int $instanceId): LazyCollection
    {
        return Club::query()->where('instance_id', $instanceId)->lazyById(200);
    }

    public function clubAccountForUpdate(int $clubId): Account
    {
        return Account::query()->where('club_id', $clubId)->lockForUpdate()->firstOrFail();
    }

    public function hasPaidWages(int $clubId, string $payday): bool
    {
        return DB::table('club_payroll_runs')->where('club_id', $clubId)->where('paid_on', $payday)->exists();
    }

    /** @return array{players: list<int>, staff: list<int>} */
    public function salariesForClub(Club $club, string $payday): array
    {
        $players = Player::query()->where('players.instance_id', $club->instance_id)
            ->where('players.club_id', $club->id)->active()
            ->join('players_contracts as contracts', 'contracts.id', '=', 'players.contract_id')
            ->whereDate('contracts.contract_start', '<=', $payday)->whereDate('contracts.contract_end', '>=', $payday)
            ->pluck('contracts.salary')->map(fn (int|string $salary): int => (int) $salary)->all();
        $staff = [];
        foreach ([StaffCoaching::class, StaffScout::class, StaffPhysio::class] as $staffClass) {
            $salaries = $staffClass::query()->where('instance_id', $club->instance_id)
                ->where('club_id', $club->id)->active()
                ->whereHas('contract', function (Builder $query) use ($payday): void {
                    $query->whereDate('contract_start', '<=', $payday)->whereDate('contract_end', '>=', $payday);
                })
                ->join('staff_contracts as contracts', 'contracts.id', '=', (new $staffClass)->getTable().'.contract_id')
                ->pluck('contracts.salary')->map(fn (int|string $salary): int => (int) $salary)->all();
            $staff = array_merge($staff, $salaries);
        }

        return ['players' => $players, 'staff' => $staff];
    }

    /** @param array{player_wages: int, staff_wages: int} $wages */
    public function recordPayment(int $clubId, string $payday, array $wages): void
    {
        DB::table('club_payroll_runs')->insert(['club_id' => $clubId, 'paid_on' => $payday] + $wages);
    }
}
