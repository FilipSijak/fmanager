<?php

namespace App\Services\TransferService;

use App\Models\Account;
use App\Models\Club;
use App\Models\Transfer;
use App\Models\TransferFinancialDetails;

class TransferFinancialSettlement
{
    public function transferMoneyBetweenClubs(Transfer $transfer)
    {
        $buyingClub = Club::where('id', $transfer->source_club_id)->first();
        $sellingClub = Club::where('id', $transfer->target_club_id)->first();
        $buyingClubAccount = Account::where('club_id', $buyingClub->id)->first();
        $sellingClubAccount = Account::where('club_id', $sellingClub->id)->first();
        $transferFinancialDetails = TransferFinancialDetails::where('transfer_id', $transfer->id)->first();

        $buyingClubAccount->future_balance -= $transferFinancialDetails->amount;
        $buyingClubAccount->transfer_budget -= $transferFinancialDetails->amount;
        $sellingClubAccount->future_balance += $transferFinancialDetails->amount;
        $sellingClubAccount->transfer_budget += $transferFinancialDetails->amount;

        if ($transferFinancialDetails->installments > 0) {
            $monthlyPayment = $transferFinancialDetails->amount / $transferFinancialDetails->installments;
            $monthlyPayment = number_format($monthlyPayment, 2, '.', '');
            $buyingClubAccount->balance -= $monthlyPayment;
            $sellingClubAccount->balance += $transferFinancialDetails->amount;

            --$transferFinancialDetails->installments;
            $transferFinancialDetails->save();
        } else {
            $buyingClubAccount->balance -= $transferFinancialDetails->amount;
            $sellingClubAccount->balance += $transferFinancialDetails->amount;
        }

        $buyingClubAccount->save();
        $sellingClubAccount->save();
    }
}
