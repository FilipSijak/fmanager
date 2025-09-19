<?php

namespace App\Services\TransferService\TransferConsiderations;

use App\Models\Club;
use App\Models\Player;
use App\Models\Transfer;
use App\Services\ClubService\FinancialAnalysis\ClubFinancialAnalysis;
use App\Services\ClubService\SquadAnalysis\SquadAnalysis;


class ClubConsideration
{
    private SquadAnalysis $squadAnalysis;
    private ClubFinancialAnalysis $clubFinancialAnalysis;

    public function __construct(
        SquadAnalysis $squadAnalysis,
        ClubFinancialAnalysis $clubFinancialAnalysis
    ) {
        $this->squadAnalysis = $squadAnalysis;
        $this->clubFinancialAnalysis = $clubFinancialAnalysis;
    }

    public function considerOffer(Transfer $transfer): bool
    {
        $player = Player::find($transfer->player_id);
        $club = Club::find($transfer->target_club_id);

        if (!$this->squadAnalysis->isAcceptableTransfer($club, $player)) {
            return false;
        }

        if (!$this->clubFinancialAnalysis->isFinanciallyAcceptableTransfer($transfer)) {
            // counteroffer?
            return false;
        }

        return true;
    }
}
