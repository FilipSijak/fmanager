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
        $performanceGameBonusesCurrentContract = $playerContract->appearance + $playerContract->clean_sheet +
                                                 $playerContract->goal + $playerContract->assist;

        $performanceGameBonusesOffer = $offerContract->appearance + $offerContract->clean_sheet +
                                       $offerContract->goal + $offerContract->assist;

        // basic conditions on player accepting/rejecting the offer

        // contract conditions
        $salaryConditionsComparison = $playerContract->salary + $performanceGameBonusesCurrentContract >
                            $offerContract->salary + $performanceGameBonusesOffer;
        $salaryDifference = $playerContract->salary + $performanceGameBonusesCurrentContract -
                            ($offerContract->salary + $performanceGameBonusesOffer);
        if ($salaryConditionsComparison) {
            return TransferStatusTypes::PLAYER_DECLINED;
        }

        // ranking conditions
        if ($player->potential / 10 < $sourceClub->rank) {
            return false;
        }


        return true;
    }

    public function isTransferAcceptable(Transfer $transfer): bool
    {
        $player = Player::where('id', $transfer->player_id)->first();
        $buyingClub = Club::where('id', $transfer->source_club_id)->first();

        // analyse player ambition
        /* @todo there is no ambition attribute on player atm so for now will use mental */
        if (!$this->playerAmbitionOnTransfer($player, $buyingClub)) {
            return false;
        }

        // analyse contract offer
        $this->analyseContractOffer();

        //get player decision

        return true;
    }

    private function playerAmbitionOnTransfer(Player $player, Club $buyingClub): bool
    {
        // club current marketing rank
        /* @todo there is no marketing rank atm so will compare against the overall rank */
        if ($player->mental / 10 - $buyingClub->rank > 2) {
            /* @todo ask for 10% for every missing point, also add a sugar daddy on club table and make players more willing to go */
            return false;
        }

        return true;
    }

    private function analyseContractOffer()
    {
        /* @todo once I have the table with financial tranfer details I can asses the contract from a player perspective */

        return true;
    }
}
