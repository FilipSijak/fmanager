<?php

namespace App\Services\FinanceService;

use App\Models\Account;
use App\Models\Club;
use App\Models\FinanceTransactions;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinanceService
{
    public function makeTransaction(
        Account $receivingAccount,
        Account $sendingAccount,
        int $amount,
    ): bool
    {
        try {
            DB::beginTransaction();

            // log transaction
            $transaction = new FinanceTransactions([
                'sending_account_id' => $sendingAccount->id,
                'receiving_account_id' => $receivingAccount->id,
                'amount' => $amount,
                'transaction_date' => Carbon::today()->toDateString(),
            ]);

            //move amount
            $sendingAccount->balance -= $amount;
            $sendingAccount->future_balance -= $amount;
            $receivingAccount->balance += $amount;
            $receivingAccount->future_balance += $amount;


            $receivingAccount->save();
            $sendingAccount->save();
            $transaction->save();
        } catch (\Exception $e) {
            DB::rollBack();

            // log error

            return false;
        }

        DB::commit();

        return true;
    }
}
