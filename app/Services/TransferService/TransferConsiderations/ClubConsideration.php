<?php

namespace App\Services\TransferService\TransferConsiderations;

use App\DataModels\ClubTransferDecision;
use App\Models\Club;
use App\Models\Player;
use App\Models\Transfer;
use App\Services\TransferService\TransferEntityAnalysis\ClubFinancialTransferAnalysis;
use App\Services\TransferService\TransferEntityAnalysis\SquadTransferAnalysis;

class ClubConsideration
{
    private SquadTransferAnalysis $squadTransferAnalysis;
    private ClubFinancialTransferAnalysis $clubFinancialTransferAnalysis;

    public function __construct(
        SquadTransferAnalysis $squadTransferAnalysis,
        ClubFinancialTransferAnalysis $clubFinancialTransferAnalysis
    ) {
        $this->squadTransferAnalysis = $squadTransferAnalysis;
        $this->clubFinancialTransferAnalysis = $clubFinancialTransferAnalysis;
    }

    public function considerOffer(Transfer $transfer): ClubTransferDecision
    {
        $player = Player::find($transfer->player_id);
        $club = Club::find($transfer->target_club_id);
        $playerImportance = $this->squadTransferAnalysis->isAcceptableTransfer($club, $player);
        $clubTransferDecision = new ClubTransferDecision();
        $financialDecision = $this->clubFinancialTransferAnalysis->isFinanciallyAcceptableTransfer($transfer, $playerImportance);

        if (!$financialDecision->isAcceptableTransfer() && $financialDecision->getCounterOffer()) {
            $clubTransferDecision->setCounterOffer($financialDecision->getCounterOffer());
        }

        return $clubTransferDecision;
    }
}
