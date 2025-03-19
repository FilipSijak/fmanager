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
use App\Services\PersonService\PersonConfig\Player\PlayerPositionConfig;
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
    /**
     * @var false
     */
    private bool $forceLuxuryBids;

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
            $this->processTransfer($transfer);
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
            $deficitPositions= $this->clubService->playerDeficitByPosition($club);

            $clubBudget = (Account::where('club_id', $club->id)->first())->transfer_budget;
            $randomChanceForLuxury = rand(1, 10);

            // if the club is covered in all positions, check if there is an opportunity on the transfer market for luxury transfers
            if (
                !$deficitPositions &&
                (($clubBudget > self::LUXURY_TRANSFER_BALANCE && $randomChanceForLuxury == 1)  || $this->forceLuxuryBids)
            ) {
                $position = PlayerPositionConfig::PLAYER_POSITIONS[rand(1,14)];

                $selectedPlayer = $this->transferSearchRepository->findPlayersWithUnprotectedContracts($club, $position);

                if (!$selectedPlayer) {
                    $selectedPlayer = $this->transferSearchRepository->getListedPlayer(
                        $club,
                        TransferTypes::PERMANENT_TRANSFER,
                        $position,
                        $clubBudget
                    );
                }

                if (!$selectedPlayer) {
                    $selectedPlayer = $this->transferSearchRepository->findLuxuryPlayersForPosition(
                        $club,
                        $position,
                        $clubBudget
                    );
                }

                if ($selectedPlayer) {
                    try {
                        DB::beginTransaction();

                        $transfer = $this->transferRepository->makeAutomaticTransferWithFinancialDetails(
                            $selectedPlayer,
                            $club
                        );

                        $clubBudget -= $transfer->amount;

                        DB::commit();
                    } catch (\Exception $exception) {
                        DB::rollBack();
                    }
                }

                continue;
            }

            foreach ($deficitPositions as $position => $deficitNumber) {
                $selectedPlayer =  $this->transferSearchRepository->findFreePlayerForPosition($club, $position);

                if (!$selectedPlayer) {
                    $selectedPlayer =  $this->transferSearchRepository->findFreePlayerForPosition($club, $position);
                }

                if (!$selectedPlayer) {
                    $players = $this->transferSearchRepository->findPlayersByPositionForClub($club, $position);
                    $selectedPlayer = $players->where('value', '<=', $clubBudget)->first();
                }


                if (!$selectedPlayer) {
                    continue;
                }

                try {
                    DB::beginTransaction();

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
