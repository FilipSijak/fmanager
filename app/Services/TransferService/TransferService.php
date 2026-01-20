<?php

namespace App\Services\TransferService;

use App\Http\Requests\CreateTransferRequest;
use App\Http\Requests\FreeTransferRequest;
use App\Models\Account;
use App\Models\Club;
use App\Models\Transfer;
use App\Repositories\TransferRepository;
use App\Repositories\TransferSearchRepository;
use App\Services\BaseService;
use App\Services\ClubService\SquadAnalysis\SquadPlayersConfig;
use App\Services\PersonService\PersonConfig\Player\PlayerPositionConfig;
use App\Services\TransferService\TransferEntityAnalysis\ClubTransferAnalysis;
use App\Services\TransferService\TransferRequest\TransferRequestValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TransferService extends BaseService
{
    private TransferRequestValidator $transferRequestValidator;
    private ClubTransferAnalysis     $clubTransferAnalysis;
    protected int|null               $instanceId;
    protected int|null               $seasonId;
    private TransferRepository       $transferRepository;
    private TransferStatusUpdates    $transferStatusUpdates;
    private TransferSearchRepository $transferSearchRepository;

    const int LUXURY_TRANSFER_BALANCE = 50000000;

    private bool $forceLuxuryBids;

    public function __construct(
        TransferRequestValidator $transferRequestValidator,
        ClubTransferAnalysis $clubTransferAnalysis,
        Request $request,
        TransferRepository $transferRepository,
        TransferStatusUpdates $transferStatusUpdates,
        TransferSearchRepository $transferSearchRepository,
        private readonly TransferServiceHandler $transferServiceHandler,
    )
    {
        $this->transferRequestValidator = $transferRequestValidator;
        $this->clubTransferAnalysis = $clubTransferAnalysis;
        $this->instanceId = $request->header('instanceId');
        $this->seasonId = $request->header('seasonId');
        $this->transferRepository = $transferRepository;
        $this->transferStatusUpdates = $transferStatusUpdates;
        $this->transferSearchRepository = $transferSearchRepository;
        $this->forceLuxuryBids = false;
    }

    public function processTransferBids()
    {
        //get all transfers from the table
        $transfers = Transfer::where('season_id', $this->seasonId)
                             ->where('transfer_type', '!=', TransferStatusTypes::TRANSFER_FAILED)
                             ->where('transfer_type', '!=', TransferStatusTypes::TRANSFER_COMPLETED)
                             ->get();

        foreach ($transfers as $transfer) {
            $this->transferServiceHandler->processTransfer($transfer);
        }
    }

    /**
     * Force luxury transfers when having a rich owner or money to invest
     */
    public function setForceLuxuryBids(bool $forceLuxuryTransferBids = true): void
    {
        $this->forceLuxuryBids = $forceLuxuryTransferBids;
    }

    /**
     * Check all non-player clubs if they need players
     * Run every day during the transfer window, run weekly outside
     */
    public function automaticTransferBids()
    {
        $clubs = Club::where('instance_id', $this->instanceId)->get();

        foreach ($clubs as $club) {
            $deficitPositions = $this->clubTransferAnalysis->playerDeficitByPosition($club);
            $clubBudget = (Account::where('club_id', $club->id)->first())->transfer_budget;
            $randomChanceForLuxury = rand(1, 10);
            // if the club is covered in all positions, check if there is an opportunity on the transfer market for luxury transfers
            if (
                !$deficitPositions &&
                (($clubBudget > self::LUXURY_TRANSFER_BALANCE && $randomChanceForLuxury == 1) || $this->forceLuxuryBids)
            ) {
                $position = PlayerPositionConfig::PLAYER_POSITIONS[rand(1,14)];

                $this->transferServiceHandler->luxuryTransferAttempt($club, $clubBudget, $position);

                continue;
            }

            if (empty($deficitPositions)) {
                continue;
            }

            $this->transferServiceHandler->playerDeficitTransferAttempt($club, $deficitPositions, $clubBudget);
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
}
