<?php

namespace App\Services\PersonService;

use App\Models\Club;
use App\Models\Player;
use App\Models\Transfer;

class PersonTransferService
{
    public function isTransferAcceptable(Transfer $transfer): bool
    {
        $player = Player::where('id', $transfer->player_id);
        $buyingClub = Club::where('id', $transfer->source_club_id);

        // analyse player ambition
        /* @todo there is no ambition attribute on player atm so for now will use mental */
        $this->playerAmbitionOnTransfer($player, $buyingClub);

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
