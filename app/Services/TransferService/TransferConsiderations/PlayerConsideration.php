<?php

namespace App\Services\TransferService\TransferConsiderations;

use App\Models\Club;
use App\Models\Player;
use App\Models\PlayerContract;
use App\Models\Transfer;
use App\Models\TransferContractOffer;

class PlayerConsideration
{
    public function considerOffer(Transfer $transfer): bool
    {
        $player = Player::where('id', $transfer->player_id)->get()->first();
        $playerContract = PlayerContract::where('id', $player->id)->get()->first();
        $sourceClub = Club::where('id', $transfer->source_club_id)->get()->first();
        $targetClub = Club::where('id', $transfer->target_club_id)->get()->first();
        $offerContract = TransferContractOffer::where('transfer_id', $transfer->id)->get()->first();
        $performanceGameBonusesCurrentContract = $playerContract->appearance + $playerContract->clean_sheet +
                                                 $playerContract->goal + $playerContract->assist;

        $performanceGameBonusesOffer = $offerContract->appearance + $offerContract->clean_sheet +
                                       $offerContract->goal + $offerContract->assist;

        // basic conditions on player accepting/rejecting the offer

        // contract conditions
        $salaryConditions = $playerContract->salary + $performanceGameBonusesCurrentContract >
                            $offerContract->salary + $performanceGameBonusesOffer;
        $salaryDifference = $playerContract->salary + $performanceGameBonusesCurrentContract -
                            ($offerContract->salary + $performanceGameBonusesOffer);
        if ($salaryConditions) {
            return false;
        }

        // ranking conditions
        if ($player->potential / 10 < $sourceClub->rank) {
            return false;
        }


        return true;
    }
}
