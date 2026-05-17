<?php

namespace App\Services\TransferService\TransferConsiderations;

use App\DataModels\ClubTransferDecision;
use App\Models\Club;
use App\Models\Player;
use App\Models\Transfer;
use App\Models\TransferContractOffer;
use App\Repositories\PlayerRepository;
use App\Services\TransferService\TransferEntityAnalysis\ClubFinancialTransferAnalysis;
use App\Services\TransferService\TransferEntityAnalysis\SquadTransferAnalysis;

class ClubConsideration
{
    private SquadTransferAnalysis $squadTransferAnalysis;
    private ClubFinancialTransferAnalysis $clubFinancialTransferAnalysis;
    private PlayerRepository $playerRepository;

    public function __construct(
        SquadTransferAnalysis $squadTransferAnalysis,
        ClubFinancialTransferAnalysis $clubFinancialTransferAnalysis,
        PlayerRepository $playerRepository,
    ) {
        $this->squadTransferAnalysis = $squadTransferAnalysis;
        $this->clubFinancialTransferAnalysis = $clubFinancialTransferAnalysis;
        $this->playerRepository = $playerRepository;
    }

    public function considerOffer(Transfer $transfer): ClubTransferDecision
    {
        $player = Player::find($transfer->player_id);
        $club = Club::find($transfer->target_club_id);
        $playerImportance = $this->squadTransferAnalysis->isAcceptableTransfer($club, $player);
        $clubTransferDecision = new ClubTransferDecision();
        $financialDecision = $this->clubFinancialTransferAnalysis->isFinanciallyAcceptableTransfer($transfer, $playerImportance);


        // if financial decision is positive and player importance is positive, ClubTransferDecision set acceptable transfer to true

        if (!$financialDecision->isAcceptableTransfer() && $financialDecision->getCounterOffer()) {
            $clubTransferDecision->setCounterOffer($financialDecision->getCounterOffer());
        }

        if ($financialDecision->isAcceptableTransfer()) {
            $clubTransferDecision->setAcceptableTransfer(true);
        }

        return $clubTransferDecision;
    }

    public function considerPlayerContractCounterOffer(Transfer $transfer): bool
    {
        $buyingClub = Club::find($transfer->source_club_id);
        $player = Player::find($transfer->player_id);
        $transferContractDetails = TransferContractOffer::where('transfer_id', $transfer->id)->firstOrFail();
        $contractOffer = (object) $this->playerRepository->contractBasedOnPotential($player);
        $positionShortage = $this->squadTransferAnalysis->positionShortage($buyingClub, $player->position);

        $countableContractFields = [
            'salary',
            'appearance',
            'assist',
            'goal',
            'clean_sheet',
            'league',
            'promotion',
            'cup',
            'el',
            'cl',
            'transfer_fee'
        ];

        $initialContractValue = 0;
        $counterOfferValue = 0;

        foreach ($countableContractFields as $field) {
            $initialContractValue += $contractOffer->{$field};
            $counterOfferValue += $transferContractDetails->{$field};
        }


        if ($positionShortage && ($counterOfferValue <=  $initialContractValue * 1.1)) {
            return true;
        }

        return false;
    }
}
