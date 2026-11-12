<?php

namespace App\Services\FinanceService;

use App\Models\Account;
use App\Models\Club;
use App\Models\Instance;
use App\Models\Player;
use App\Models\StaffCoaching;
use App\Models\StaffPhysio;
use App\Models\StaffScout;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    public function __construct(private readonly AccountingService $accounting) {}

    public function payDueWages(Instance $instance): void
    {
        $date = CarbonImmutable::parse($instance->instance_date)->startOfDay();
        if (! $date->isMonday()) {
            return;
        }

        Club::query()->where('instance_id', $instance->id)->lazyById(200)->each(function (Club $club) use ($date): void {
            DB::transaction(function () use ($club, $date): void {
                $account = Account::query()->where('club_id', $club->id)->lockForUpdate()->firstOrFail();
                $payday = $date->toDateString();
                if (DB::table('club_payroll_runs')->where('club_id', $club->id)->where('paid_on', $payday)->exists()) {
                    return;
                }

                $playerWages = (int) Player::query()->where('players.instance_id', $club->instance_id)
                    ->where('players.club_id', $club->id)->active()
                    ->join('players_contracts as contracts', 'contracts.id', '=', 'players.contract_id')
                    ->whereDate('contracts.contract_start', '<=', $payday)->whereDate('contracts.contract_end', '>=', $payday)
                    ->sum('contracts.salary');
                $staffWages = 0;
                foreach ([StaffCoaching::class, StaffScout::class, StaffPhysio::class] as $staffClass) {
                    $staffWages += (int) $staffClass::query()->where('instance_id', $club->instance_id)
                        ->where('club_id', $club->id)->active()
                        ->whereHas('contract', function (Builder $query) use ($payday): void {
                            $query->whereDate('contract_start', '<=', $payday)->whereDate('contract_end', '>=', $payday);
                        })
                        ->join('staff_contracts as contracts', 'contracts.id', '=', (new $staffClass)->getTable().'.contract_id')
                        ->sum('contracts.salary');
                }

                foreach (['player_wages' => $playerWages, 'staff_wages' => $staffWages] as $category => $amount) {
                    if ($amount > 0) {
                        $this->accounting->expense($account, $amount, $date, $category, 'payroll:'.$club->id.':'.$payday.':'.$category);
                    }
                }

                DB::table('club_payroll_runs')->insert([
                    'club_id' => $club->id,
                    'paid_on' => $payday,
                    'player_wages' => $playerWages,
                    'staff_wages' => $staffWages,
                ]);
            });
        });
    }
}
