<?php

namespace App\Services\TransferService;

use App\Http\Requests\CreateTransferRequest;
use App\Models\Club;
use App\Models\Instance;
use App\Models\Transfer;
use App\Repositories\TransferRepository;
use App\Services\ClubService\ClubService;
use App\Services\ClubService\SquadAnalysis\SquadAnalysis;
use App\Services\PersonService\PersonTransferService;
use App\Services\TransferService\TransferRequest\TransferRequestValidator;
use Illuminate\Http\Request;

class TransferService
{
    private TransferRequestValidator $transferRequestValidator;
    private ClubService              $clubService;
    private string|null              $instanceId;
    private string|null              $seasonId;
    private PersonTransferService    $personTransferService;

    public function __construct(
        TransferRequestValidator $transferRequestValidator,
        ClubService $clubService,
        PersonTransferService $personTransferService,
        SquadAnalysis $squadAnalysis,
        Request $request,
        TransferRepository $transferRepository
    )
    {
        $this->transferRequestValidator = $transferRequestValidator;
        $this->clubService = $clubService;
        $this->personTransferService = $personTransferService;
        $this->instanceId = $request->header('instanceId');
        $this->seasonId = $request->header('seasonId');
        $this->transferRepository = $transferRepository;
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
                $transfer->season_id = $this->seasonId;
                $transfer->source_club_id = $club->id;
                $transfer->target_club_id = $player->club_id;
                $transfer->player_id = $player->id;
                $transfer->offer_date = Instance::find($this->instanceId)->instance_date;
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

    /**
     * SOURCE_CLUB - club that made an offer
     * TARGET_CLUB - club that owns the player
     */
    public function processTransfer(Transfer $transfer)
    {
        // if waiting for target club approval
        if ($transfer->transfer_status == TransferStatusTypes::WAITING_TARGET_CLUB) {
            $club = Club::where('id', $transfer->target_club_id)->first();

            if ($this->clubService->clubSellingDecision($transfer)) {
                // club approves, update status
            }
        }

        // if waiting for player approval
        if ($transfer->transfer_status == TransferStatusTypes::WAITING_PLAYER) {
            if ($this->personTransferService->isTransferAcceptable($transfer)) {
                // update transfer with person approved
            }
        }

        // counteroffer
        if ($transfer->transfer_status == TransferStatusTypes::WAITING_SOURCE_CLUB) {
            //is counteroffer acceptable
        }
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

    public function startTransferNegotiations(CreateTransferRequest $request)
    {
        $this->transferRepository->storeTransfer($request);
    }
}
