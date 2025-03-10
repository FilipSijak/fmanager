<?php

namespace App\Services\TransferService;

use App\Http\Requests\CreateTransferRequest;
use App\Http\Requests\FreeTransferRequest;
use App\Models\Account;
use App\Models\Club;
use App\Models\Instance;
use App\Models\Player;
use App\Models\Transfer;
use App\Models\TransferContractOffer;
use App\Models\TransferFinancialDetails;
use App\Repositories\TransferRepository;
use App\Repositories\TransferSearchRepository;
use App\Services\BaseService;
use App\Services\ClubService\ClubService;
use App\Services\TransferService\TransferRequest\TransferRequestValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransferService extends BaseService
{
    private TransferRequestValidator $transferRequestValidator;
    private ClubService              $clubService;
    protected int|null               $instanceId;
    protected int|null               $seasonId;
    private TransferRepository       $transferRepository;
    private TransferStatusUpdates    $transferStatusUpdates;
    const LUXURY_TRANSFER_BALANCE = 50000000;
    private TransferSearchRepository $transferSearchRepository;

    public function __construct(
        TransferRequestValidator $transferRequestValidator,
        ClubService $clubService,
        Request $request,
        TransferRepository $transferRepository,
        TransferStatusUpdates $transferStatusUpdates,
        TransferSearchRepository $transferSearchRepository
    )
    {
        $this->transferRequestValidator = $transferRequestValidator;
        $this->clubService = $clubService;
        $this->instanceId = $request->header('instanceId');
        $this->seasonId = $request->header('seasonId');
        $this->transferRepository = $transferRepository;
        $this->transferStatusUpdates = $transferStatusUpdates;
        $this->transferSearchRepository = $transferSearchRepository;
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
     * Run every day during the transfer window, run weekly outside
     */
    public function automaticTransferBids()
    {
        $clubs = Club::where('instance_id', $this->instanceId)->get();

        // analyse clubs missing numbers for positions
        foreach ($clubs as $club) {
            $deficitPositions= $this->clubService->playerDeficitByPosition($club);

            $clubBudget = (Account::where('club_id', $club->id)->first())->transfer_budget;

            if (!$deficitPositions) {
                $randomChanceForLuxury = rand(1, 10);
                if ($clubBudget > self::LUXURY_TRANSFER_BALANCE && $randomChanceForLuxury == 1) {
                    // look for players that with better rank for a position with the lowest potential within the club
                }
                continue;
            }

            foreach ($deficitPositions as $position => $deficitNumber) {

                // find suitable player and make a transfer
                $players = $this->transferSearchRepository->findPlayersByPositionForClub($club, $position);
                $selectedPlayer = $players->where('value', '<=', $clubBudget)->first();
                // if there is a bigger budget go for another transfer

                if (!$selectedPlayer) {
                    continue;
                }

                DB::beginTransaction();

                try {
                    $transfer = $this->transferRepository->makeAutomaticTransferWithFinancialDetails($selectedPlayer, $club);

                    $clubBudget -= $transfer->amount;

                    DB::commit();
                } catch (\Exception $exception) {
                    DB::rollBack();
                }
            }
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
