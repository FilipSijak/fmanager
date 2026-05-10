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
    const MAX_COUNTER_OFFER = 2;
    const MAX_AMBITION_DIFF = 4;

    const COUNTABLE_CONTRACT_FIELDS = [
        'salary',
        'appearance',
        'assist',
        'goal',
        'clean_sheet',
        'league',
        'promotion',
        'pc_promotion_salary_raise',
        'cup',
        'el',
        'cl',
        'loan_contribution_pc'
    ];

    public function considerOffer(Transfer $transfer): PlayerContractDecision
    {
        $player = Player::where('id', $transfer->player_id)->get()->first();
        $playerContract = $player->contract()->first();
        $sourceClub = Club::where('id', $transfer->source_club_id)->get()->first();
        $offerContract = TransferContractOffer::where('transfer_id', $transfer->id)->get()->first();

        return $this->ifOfferAcceptable($offerContract, $playerContract, $player, $sourceClub, $transfer);
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

        if (($player->ambition - $sourceClub->rank) > self::MAX_AMBITION_DIFF) {
            $playerContractDecision->acceptableTransfer = false;
            $playerContractDecision->counterOffer = 0;

            return $playerContractDecision;
        }

        if ($requiredOffer > $offerTotal) {

            if ($offerContract->counter_offered >= self::MAX_COUNTER_OFFER) {
                // player previously rejected the offer and made a counteroffer, now rejecting the transfer outright
                $playerContractDecision->acceptableTransfer = false;
                return $playerContractDecision;
            }

            $playerContractDecision->counterOffer = $requiredOffer;

            $contractAmbitionDiffPercentage = $player->ambition / $sourceClub->rank;

            $offerContract->counter_offered = $offerContract->counter_offered + 1;

            foreach (self::COUNTABLE_CONTRACT_FIELDS as $field) {
                $offerContract->{$field} = (int) round($offerContract->{$field} * $contractAmbitionDiffPercentage);
            }

            $offerContract->save();

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

        if ($player->ambition >= $sourceClub->rank) {
            $rankDiff = ($player->ambition - $sourceClub->rank) / 10;
            $requiredOffer = (($currentTotal) * $rankDiff) + $currentTotal;
        }

        return $requiredOffer;
    }
}
