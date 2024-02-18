<?php

namespace App\Services\ClubService;

use App\Models\Club;
use App\Models\Player;
use App\Models\Transfer;
use App\Services\ClubService\SquadAnalysis\SquadAnalysis;

class ClubService
{
    private SquadAnalysis $squadAnalysis;

    public function __construct(
        SquadAnalysis $squadAnalysis
    )
    {
        $this->squadAnalysis = $squadAnalysis;
    }

    public function clubSellingDecision(Transfer $transfer): bool
    {
        // analyse club squad
        $player = Player::find($transfer->player_id);
        $club = Club::find($transfer->target_club_id);

        if (!$this->squadAnalysis->isAcceptableTransfer($club, $player)) {
            return false;
        }

        $this->squadAnalysis->isAcceptableTransfer($club, $player);
        // analyse financial offer

        return true;
    }

    public function clubFinancialSummary()
    {

    }

    public function transferHandler()
    {

    }

    public function loanHandler()
    {

    }
}
