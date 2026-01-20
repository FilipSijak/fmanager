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

    public function playerDeficitByPosition(Club $club): bool|Collection
    {
        if (empty($this->squadTransferAnalysis->optimalNumbersCheckByPosition($club))) {
            return false;
        }

        return Collect($this->squadTransferAnalysis->optimalNumbersCheckByPosition($club));
    }
}
