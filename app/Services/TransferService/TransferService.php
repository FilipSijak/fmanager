<?php

namespace App\Services\TransferService;

use App\Http\Requests\CreateTransferRequest;
use App\Http\Requests\FreeTransferRequest;
use App\Models\Club;
use App\Models\Instance;
use App\Models\Transfer;
use App\Repositories\TransferRepository;
use App\Services\BaseService;
use App\Services\ClubService\ClubService;
use App\Services\TransferService\TransferRequest\TransferRequestValidator;
use Illuminate\Http\Request;

class TransferService extends BaseService
{
    private TransferRequestValidator $transferRequestValidator;
    private ClubService              $clubService;
    protected int|null               $instanceId;
    protected int|null               $seasonId;
    private TransferRepository       $transferRepository;
    private TransferStatusUpdates    $transferStatusUpdates;

    public function __construct(
        TransferRequestValidator $transferRequestValidator,
        ClubService $clubService,
        Request $request,
        TransferRepository $transferRepository,
        TransferStatusUpdates $transferStatusUpdates
    )
    {
        $this->transferRequestValidator = $transferRequestValidator;
        $this->clubService = $clubService;
        $this->instanceId = $request->header('instanceId');
        $this->seasonId = $request->header('seasonId');
        $this->transferRepository = $transferRepository;
        $this->transferStatusUpdates = $transferStatusUpdates;
    }

    public function processTransferBids()
    {
        //get all transfers from the table
        $transfers = Transfer::where('season_id', $this->seasonId)
                             ->where('transfer_type', '!=', TransferStatusTypes::TRANSFER_FAILED)
                             ->where('transfer_type', '!=', TransferStatusTypes::TRANSFER_COMPLETED)
                             ->get();

        foreach ($transfers as $transfer) {
            $this->processTransfer($transfer);
        }
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

    public function startTransferNegotiations(CreateTransferRequest $request): void
    {
        $this->transferRepository->storeTransfer($request);
    }

    public function freeTransferRequest(FreeTransferRequest $request)
    {
        $this->transferRepository->storeFreeTransfer($request);
    }

    private function processTransfer(Transfer $transfer): void
    {
        switch ($transfer->transfer_type) {
            case TransferTypes::FREE_TRANSFER:
                $this->transferStatusUpdates->freeTransferUpdates($transfer);
                break;
            case TransferTypes::LOAN_TRANSFER:
                $this->transferStatusUpdates->loanTransferUpdates($transfer);
                break;
            default:
                $this->transferStatusUpdates->permanentTransferUpdates($transfer);
        }
    }
}
