<?php

namespace App\Services\TransferService\TransferConsiderations;

use App\DataModels\PlayerContractDecision;
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
        $playerContract = $player->contract()->first();
        $sourceClub = Club::where('id', $transfer->source_club_id)->get()->first();
        $targetClub = Club::where('id', $transfer->target_club_id)->get()->first();
        $offerContract = TransferContractOffer::where('transfer_id', $transfer->id)->get()->first();

        $playerAmbitionDecision = false;
        $requiredOffer = $playerContract->salary;

        // if player potential is < source club rank - player accepts
        // if player p > source club rank - adjust salary expectations
        // if salary doesn't cover it

        if ($player->potential / 10 <= $sourceClub->rank | $player->ambition <= $sourceClub->rank / 10) {
            $playerAmbitionDecision = true;
        }

        $playerDecision = $this->ifOfferAcceptable($offerContract, $playerContract, $player, $sourceClub, $transfer);

        if ($playerDecision->counterOffer) {

            if ($transfer->transfer_status == TransferStatusTypes::PLAYER_COUNTEROFFER) {
                // player previously rejected the offer and made a counteroffer, now rejecting the transfer outright

                return TransferStatusTypes::PLAYER_DECLINED->value;
            }

                return TransferStatusTypes::PLAYER_COUNTEROFFER->value;
        }

        return TransferStatusTypes::WAITING_PAPERWORK->value;
    }

    public function ifOfferAcceptable(
        TransferContractOffer $offerContract,
        PlayerContract $currentContract,
        Player $player,
        Club $sourceClub,
        Transfer $transfer
    ): PlayerContractDecision
    {
        $playerContractDecision = new PlayerContractDecision();
        $performanceGameBonusesOffer = $offerContract->appearance + $offerContract->clean_sheet +
                                       $offerContract->goal + $offerContract->assist;

        $requiredOffer = $this->requiredOffer($currentContract, $player, $sourceClub);
        $offerTotal = $offerContract->salary + $performanceGameBonusesOffer;

        if ($requiredOffer > $offerTotal) {
            if ($transfer->transfer_status == TransferStatusTypes::PLAYER_COUNTEROFFER) {
                // player previously rejected the offer and made a counteroffer, now rejecting the transfer outright
                $playerContractDecision->acceptableTransfer = false;
                return $playerContractDecision;
            }

            $playerContractDecision->counterOffer = $requiredOffer;

            return $playerContractDecision;
        }

        $playerContractDecision->acceptableTransfer = true;

        return $playerContractDecision;
    }

    private function requiredOffer
    (
        PlayerContract $currentContract,
        Player $player,
        Club $sourceClub
    ): int
    {
        $performanceGameBonusesCurrentContract = $currentContract->appearance + $currentContract->clean_sheet +
            $currentContract->goal + $currentContract->assist;

        $currentTotal = $requiredOffer = $currentContract->salary + $performanceGameBonusesCurrentContract;

        if ($player->potential / 10 >= $sourceClub->rank) {
            $rankDiff = ($player->potential / 10 - $sourceClub->rank) / 10;
            $requiredOffer = (($currentTotal) * $rankDiff) + $currentTotal;
        }

        return $requiredOffer;
    }
}
