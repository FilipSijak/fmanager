<?php

namespace App\Services\TransferService\TransferEntityAnalysis;

use App\Models\Club;
use App\Models\Player;

class PlayerValuation
{
    public function buyingClubValuation(Player $player, Club $club): int
    {
        $account = $club->account()->first();

        if ($account->transfer_budget < $player->value + ($player->value * 0.2)) {
            return 0;
        }

        return roundAmount($player->value + ($player->value * 0.1));
    }
}
