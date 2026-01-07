<?php

namespace App\Services\TransferService\TransferEntityAnalysis;

use App\Models\Club;
use App\Models\Player;
use App\Models\Transfer;
use Illuminate\Support\Collection;

class ClubTransferAnalysis
{
    private SquadTransferAnalysis $squadTransferAnalysis;
    private ClubFinancialTransferAnalysis $financialTransferAnalysis;

    public function __construct(
        SquadTransferAnalysis $squadTransferAnalysis,
        ClubFinancialTransferAnalysis $financialTransferAnalysis,
    )
    {
        $this->squadTransferAnalysis = $squadTransferAnalysis;
        $this->financialTransferAnalysis = $financialTransferAnalysis;
    }

    public function clubSellingDecision(Transfer $transfer): bool
    {
        $player = Player::find($transfer->player_id);
        $club = Club::find($transfer->target_club_id);

        if (!$this->squadTransferAnalysis->isAcceptableTransfer($club, $player)) {
            return false;
        }

        if (!$this->financialTransferAnalysis->isFinanciallyAcceptableTransfer($transfer)) {
            // counteroffer?
            return false;
        }

        return true;
    }

    public function playerDeficitByPosition(Club $club): bool|Collection
    {
        if (empty($this->squadTransferAnalysis->optimalNumbersCheckByPosition($club))) {
            return false;
        }

        return Collect($this->squadTransferAnalysis->optimalNumbersCheckByPosition($club));
    }
}
