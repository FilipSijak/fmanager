<?php

namespace App\Services\TransferService\TransferEntityAnalysis;

use App\Models\Club;
use App\Models\Player;
use App\Models\Transfer;
use App\Services\TransferService\TransferStatusTypes;

class PlayerValuation
{
    public function buyingClubValuation(Player $player, Club $club): int
    {
        $account = $club->account()->first();
        $sellingClubValuation = $this->sellingClubValuation($player);

        if ($account->transfer_budget < $sellingClubValuation) {
            return 0;
        }

        $currentPotentialValue = $this->valuationByAttribute($player->potential);

        $maxPotentialValue = $this->valuationByAttribute($player->max_potential);
        $marketingRankValue = $this->valuationByAttribute($player->marketing_rank);

        $amount = $currentPotentialValue > $maxPotentialValue ? $currentPotentialValue:
            $maxPotentialValue - (($maxPotentialValue - $currentPotentialValue) / 2);

        $amount = $marketingRankValue > $amount ? $amount + (($maxPotentialValue - $amount) / 2) :
            $amount - (($amount - $maxPotentialValue) /2);

       $amountSize = strlen((string) $amount);

       if ($amountSize <= 6) {
           return round($amount, -3);
       }

        return round($amount, -6);
    }

    public function sellingClubValuation(Player $player): int
    {
        for ($k = 0.1, $i = 10; $i <= 200; $i +=10, $k += 0.06) {
            if ($player->potential > $i) {
                continue;
            }

            $value = 180 * round(pow($player->potential, $k), 2) * 1000;
            break;
        }

        return $value;
    }

    private function valuationByAttribute(int $attributeValue) {
        for ($k = 0.1, $i = 10; $i <= 200; $i +=10, $k += 0.06) {
            if ($attributeValue > $i) {
                continue;
            }

            $value = 180 * round(pow($attributeValue, $k), 2) * 1000;
            break;
        }

        return $value;
    }
}
