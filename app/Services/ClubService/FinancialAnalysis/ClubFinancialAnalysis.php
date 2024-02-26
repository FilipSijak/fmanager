<?php

namespace App\Services\ClubService\FinancialAnalysis;

use App\Models\Account;
use App\Models\Club;
use App\Models\Transfer;

class ClubFinancialAnalysis
{
    public function isFinanciallyAcceptableTransfer(Transfer $transfer): bool
    {
        $club = Club::find($transfer->target_club_id);

        if ($transfer->amount > $this->getClubTransfersLimit($club) ) {
            return false;
        }

        // does selling club need money?

        // is player overpriced and it's better to sell?

        return true;
    }

    public function getClubTransfersLimit(Club $club): int
    {
        $account = Account::where('club_id', $club->id)->first();

        return $account->transfer_budget;
    }

    public function getClubFinancialReport(Club $club)
    {

    }
}
