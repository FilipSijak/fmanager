<?php

namespace App\Services\TransferService\TransferEntityAnalysis;

use App\Models\Club;
use App\Models\Player;

class PlayerValuation
{
    public static function buyingClubValuation(Player $player, Club $club, bool $urgentTransfer): int
    {
        $account = $club->account()->first();
        $multiplier = $urgentTransfer ? 0.3 : 0.1;
        $valuation = $player->value * (1 + $multiplier);

        if ($account->transfer_budget <$valuation) {
            return 0;
        }

        return roundAmount($valuation);
    }
}
