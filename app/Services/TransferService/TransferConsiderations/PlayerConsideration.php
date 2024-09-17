<?php

namespace App\Services\TransferService\TransferConsiderations;

use App\Models\Club;
use App\Models\Player;
use App\Models\PlayerContract;
use App\Models\Transfer;
use App\Models\TransferContractOffer;
use App\Services\TransferService\TransferStatusTypes;

class PlayerConsideration
{
    public function considerOffer(Transfer $transfer): int
    {
        $player = Player::where('id', $transfer->player_id)->get()->first();
        $playerContract = PlayerContract::where('id', $player->id)->get()->first();
        $sourceClub = Club::where('id', $transfer->source_club_id)->get()->first();
        $targetClub = Club::where('id', $transfer->target_club_id)->get()->first();
        $offerContract = TransferContractOffer::where('transfer_id', $transfer->id)->get()->first();

        // ranking conditions
        if ($player->potential / 10 > $sourceClub->rank) {
            // @todo
            // add and check player ambition and loyalty (ignore loyalty for free and loan transfers)
            // ambition needs to be >= club rank + 3 to decline
            // add age consideration
        }

        if (!$this->ifOfferAcceptable($offerContract, $playerContract, $player, $sourceClub)) {
            return TransferStatusTypes::PLAYER_DECLINED;
        }

        return TransferStatusTypes::WAITING_PAPERWORK;
    }

    public function ifOfferAcceptable(
        TransferContractOffer $offerContract,
        PlayerContract $currentContract,
        Player $player,
        Club $sourceClub
    ): bool
    {
        $performanceGameBonusesCurrentContract = $currentContract->appearance + $currentContract->clean_sheet +
                                                 $currentContract->goal + $currentContract->assist;

        $performanceGameBonusesOffer = $offerContract->appearance + $offerContract->clean_sheet +
                                       $offerContract->goal + $offerContract->assist;

        $currentTotal = $requiredOffer = $currentContract->salary + $performanceGameBonusesCurrentContract;
        $offerTotal = $offerContract->salary + $performanceGameBonusesOffer;

        if ($player->potential / 10 >= $sourceClub->rank) {
            $rankDiff = ($player->potential / 10 - $sourceClub->rank) / 10;
            $requiredOffer = (($currentTotal) * $rankDiff) + $currentTotal;
        }

        if ($requiredOffer > $offerTotal) {
            return false;
        }

        return true;
    }
}
