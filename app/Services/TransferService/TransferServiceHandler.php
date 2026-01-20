<?php

namespace App\Services\TransferService;

use App\Models\Club;
use App\Models\Transfer;
use App\Repositories\TransferRepository;
use App\Repositories\TransferSearchRepository;
use App\Services\ClubService\SquadAnalysis\SquadPlayersConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class TransferServiceHandler
{
    public function __construct(
        private readonly TransferSearchRepository $transferSearchRepository,
        private readonly TransferRepository $transferRepository
    ) {}

    public function playerDeficitTransferAttempt(Club $club, Collection $deficitPositions, int $clubBudget): void
    {
        foreach ($deficitPositions as $position => $deficitNumber) {
            $urgentTransfer = $this->isUrgentTransfer($position, $deficitNumber);
            $playerSelection = $this->findSuitablePlayer($club, $position, $clubBudget);

            if (!$playerSelection) {
                continue;
            }

            $this->executeTransfer($club, $playerSelection, $urgentTransfer);
        }
    }

    public function luxuryTransferAttempt(Club $club, int $clubBudget, string $position): void
    {
        $selectedPlayer = $this->findLuxuryTargetPlayer($club, $position, $clubBudget);

        if (!$selectedPlayer) {
            return;
        }

        $this->executeTransfer($club, $selectedPlayer, false);
    }

    public function processTransfer(Transfer $transfer): void
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

    private function isUrgentTransfer(string $position, int $deficitNumber): bool
    {
        return SquadPlayersConfig::POSITION_COUNT[$position] - $deficitNumber
               <= SquadPlayersConfig::MIN_PLAYER_COUNT_BY_POSITION[$position];
    }

    private function findSuitablePlayer(Club $club, string $position, int $clubBudget): ?array
    {
        // Try free transfer first
        $player = $this->transferSearchRepository->findFreePlayerForPosition($club, $position);
        if ($player) {
            return ['player' => $player, 'type' => TransferTypes::FREE_TRANSFER];
        }

        // Try loan transfer
        $player = $this->transferSearchRepository->findListedLoanPlayers($club, $position);
        if ($player) {
            return ['player' => $player, 'type' => TransferTypes::LOAN_TRANSFER];
        }

        // Try listed permanent transfer
        $player = $this->transferSearchRepository->findListedPlayer(
            $club,
            TransferTypes::PERMANENT_TRANSFER,
            $position,
            $clubBudget
        );
        if ($player) {
            return ['player' => $player, 'type' => TransferTypes::PERMANENT_TRANSFER];
        }

        // Try any permanent transfer within budget
        $players = $this->transferSearchRepository->findPlayersByPositionForClub($club, $position);
        $player = $players->where('value', '<=', $clubBudget)->first();

        return $player ? ['player' => $player, 'type' => TransferTypes::PERMANENT_TRANSFER] : null;
    }

    private function findLuxuryTargetPlayer(Club $club, string $position, int $clubBudget): ?array
    {
        $selectedPlayer = $this->transferSearchRepository->findPlayersWithUnprotectedContracts($club, $position, $clubBudget);

        if ($selectedPlayer) {
            return ['player' => $selectedPlayer, 'type' => TransferTypes::PERMANENT_TRANSFER];
        }

        $selectedPlayer = $this->transferSearchRepository->findListedPlayer
        (
            $club,
            TransferTypes::PERMANENT_TRANSFER,
            $position,
            $clubBudget
        );

        if ($selectedPlayer) {
            return ['player' => $selectedPlayer, 'type' => TransferTypes::PERMANENT_TRANSFER];
        }

        $selectedPlayer = $this->transferSearchRepository->findLuxuryPlayersForPosition(
            $club,
            $position,
            $clubBudget
        );

        return $selectedPlayer ? ['player' => $selectedPlayer, 'type' => TransferTypes::PERMANENT_TRANSFER] : null;
    }

    private function executeTransfer(Club $club, array $playerSelection, bool $urgentTransfer): void
    {
        try {
            DB::beginTransaction();

            $transfer = $this->transferRepository->makeAutomaticTransferWithFinancialDetails(
                $playerSelection['player'],
                $club,
                $playerSelection['type'],
                $urgentTransfer
            );

            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            // Consider logging the exception
            report($exception);
        }
    }
}
