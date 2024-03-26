<?php

namespace App\Services\TransferService;

use App\Models\Club;
use App\Models\Instance;
use App\Models\Transfer;
use App\Services\ClubService\ClubService;
use App\Services\ClubService\SquadAnalysis\SquadAnalysis;
use App\Services\TransferService\TransferRequest\TransferRequestValidator;

class TransferService
{
    private TransferRequestValidator $transferRequestValidator;
    private ClubService              $clubService;

    public function __construct(
        TransferRequestValidator $transferRequestValidator,
        ClubService $clubService,
        SquadAnalysis $squadAnalysis
    )
    {
        $this->transferRequestValidator = $transferRequestValidator;
        $this->clubService = $clubService;
    }

    public function processTransferBids()
    {
        //get all transfers from the table
        $transfers = Transfer::where('season_id', 1)->get();

        foreach ($transfers as $transfer) {
            $this->processTransfer($transfer);
        }

        // read offers and run analysis for clubs/players to make decisions

        // upd

        // go to transfers and send events to  source/target clubs/ players for them to make decisions
    }

    /**
     * Check all non-player clubs if they need players
     */
    public function automaticTransferBids(Instance $instance)
    {
        // get all clubs for the instance
        $clubs = Club::where('instance_id', $instance->id)->get();

        // analyse clubs missing numbers for positions
        foreach ($clubs as $club) {
            $player = $this->clubService->playerForDeficitPosition($club);

            if (!$player) {
                continue;
            }

            try {
                $transfer = new Transfer();
                $transfer->season_id = 1;
                $transfer->source_club_id = $club->id;
                $transfer->target_club_id = $player->club_id;
                $transfer->player_id = $player->id;
                $transfer->offer_date = Instance::find(1)->instance_date;
                $transfer->transfer_type = TransferTypes::PERMANENT_TRANSFER;
                $transfer->amount = $player->value;

                $transfer->save();
            } catch (\Exception $exception) {
                dd($player);
            }
        }

        // do a luxury request if club has a lot of extra money
        // filter clubs with loads of money
    }

    public function processTransfer(Transfer $transfer)
    {
        // if waiting for target club approval
        if ($transfer->transfer_status == TransferStatusTypes::WAITING_TARGET_CLUB) {
            $club = Club::where('id', $transfer->target_club_id == 1)->first();

            if ($this->clubService->clubSellingDecision($transfer)) {

            }
        }

        // if waiting for player approval
        // analyse contract offer
        // analyse player ambition

    }

    public function makeTransferRequest(array $requestParams)
    {
        switch ($requestParams) {
            case TransferTypes::FREE_TRANSFER:
                $validationErrors = $this->transferRequestValidator->validateFreeTransferRequest($requestParams);

                if (!empty($validationErrors)) {
                    return false;
                }

                break;
            case TransferTypes::LOAN_TRANSFER:
                $validationErrors = $this->transferRequestValidator->validateLoanTransferRequest($requestParams);

                if (!empty($validationErrors)) {
                    return false;
                }

                break;
            case TransferTypes::PERMANENT_TRANSFER:
                $validationErrors = $this->transferRequestValidator->validatePermanentTransferRequest($requestParams);

                if (!empty($validationErrors)) {
                    return false;
                }

                break;
        }
    }

    public function transferRequestDecision($requestParams)
    {
        switch ($requestParams['type']) {
            case TransferTypes::FREE_TRANSFER:
                // player decision

                break;
            case TransferTypes::LOAN_TRANSFER:
                // club analysis (availability)
                // player decision

                break;
            case TransferTypes::PERMANENT_TRANSFER:
                // club analysis (valuation, availability, budget, etc.)
                // player decision

                break;
        }
    }
}
