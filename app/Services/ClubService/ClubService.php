<?php

namespace App\Services\ClubService;

use App\Models\Club;
use App\Models\Player;
use App\Models\Transfer;
use App\Services\ClubService\FinancialAnalysis\ClubFinancialAnalysis;
use App\Services\ClubService\SquadAnalysis\SquadAnalysis;

class ClubService
{
    private SquadAnalysis         $squadAnalysis;
    private ClubFinancialAnalysis $financialAnalysis;

    public function __construct(
        SquadAnalysis $squadAnalysis,
        ClubFinancialAnalysis $financialAnalysis
    )
    {
        $this->squadAnalysis = $squadAnalysis;
        $this->financialAnalysis = $financialAnalysis;
    }

    public function clubSellingDecision(Transfer $transfer): bool
    {
        $player = Player::find($transfer->player_id);
        $club = Club::find($transfer->target_club_id);

        if (!$this->squadAnalysis->isAcceptableTransfer($club, $player)) {
            return false;
        }

        if (!$this->financialAnalysis->isFinanciallyAcceptableTransfer($transfer)) {
            // counteroffer?
            return false;
        }

        return true;
    }

    public function transferHandler()
    {

    }

    public function loanHandler()
    {

    }
}
