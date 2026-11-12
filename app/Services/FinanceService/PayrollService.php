<?php

namespace App\Services\FinanceService;

use App\Models\Club;
use App\Models\Instance;
use App\Repositories\PayrollRepository;
use App\Services\FinanceService\Domain\PayrollCalculator;

class PayrollService
{
    public function __construct(
        private readonly AccountingService $accounting,
        private readonly PayrollRepository $repository,
        private readonly PayrollCalculator $calculator,
    ) {}

    public function payDueWages(Instance $instance): void
    {
        $date = $this->calculator->payday($instance->instance_date);
        if ($date === null) {
            return;
        }

        $this->repository->clubs($instance->id)->each(function (Club $club) use ($date): void {
            $this->repository->transaction(function () use ($club, $date): void {
                $account = $this->repository->clubAccountForUpdate($club->id);
                $payday = $date->toDateString();
                if ($this->repository->hasPaidWages($club->id, $payday)) {
                    return;
                }

                $wages = $this->calculator->wages($this->repository->salariesForClub($club, $payday));
                foreach ($this->calculator->payments($club->id, $date, $wages) as $payment) {
                    $this->accounting->expense($account, $payment['amount'], $date, $payment['category'], $payment['reference']);
                }

                $this->repository->recordPayment($club->id, $payday, $wages);
            });
        });
    }
}
