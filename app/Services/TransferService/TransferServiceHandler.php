<?php

namespace App\Services\TransferService;

use App\Models\Club;
use App\Models\Transfer;
use App\Repositories\TransferSearchRepository;
use App\Services\ClubService\SquadAnalysis\SquadPlayersConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

readonly class TransferServiceHandler
{
    public function __construct(
        private TransferSearchRepository $transferSearchRepository,
        private TransferWorkflow $transferWorkflow,
        private TransferStatusUpdates $transferStatusUpdates,
    ) {}

    public function playerDeficitTransferAttempt(Club $club, Collection $deficitPositions, int $clubBudget): void
    {
        foreach ($deficitPositions as $position => $deficitNumber) {
            $urgentTransfer = $this->isUrgentTransfer($position, $deficitNumber);
            $playerSelection = $this->findSuitablePlayer($club, $position, $clubBudget);

            if (! $playerSelection) {
                continue;
            }

            $this->executeTransfer($club, $playerSelection, $urgentTransfer);
        }
    }

    public function luxuryTransferAttempt(Club $club, int $clubBudget, string $position): void
    {
        $selectedPlayer = $this->findLuxuryTargetPlayer($club, $position, $clubBudget);

        if (! $selectedPlayer) {
            return;
        }

        $this->executeTransfer($club, $selectedPlayer, false);
    }

    public function processTransfer(Transfer $transfer): void
    {
        switch ($transfer->transfer_type) {
            case TransferType::FREE_TRANSFER->value:
                $this->transferStatusUpdates->freeTransferUpdates($transfer);
                break;
            case TransferType::LOAN_TRANSFER->value:
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
            return ['player' => $player, 'type' => TransferType::FREE_TRANSFER->value];
        }

        // Try loan transfer
        $player = $this->transferSearchRepository->findListedLoanPlayer($club, $position);
        if ($player) {
            return ['player' => $player, 'type' => TransferType::LOAN_TRANSFER->value];
        }

        // Try listed permanent transfer
        $player = $this->transferSearchRepository->findListedPlayer(
            $club,
            TransferType::PERMANENT_TRANSFER,
            $position,
            $clubBudget
        );
        if ($player) {
            return ['player' => $player, 'type' => TransferType::PERMANENT_TRANSFER->value];
        }

        // Try any permanent transfer within budget
        $players = $this->transferSearchRepository->findTransferTargetsByPosition($club, $position);
        $player = $players->where('value', '<=', $clubBudget)->first();

        return $player ? ['player' => $player, 'type' => TransferType::PERMANENT_TRANSFER->value] : null;
    }

    private function findLuxuryTargetPlayer(Club $club, string $position, int $clubBudget): ?array
    {
        $selectedPlayer = $this->transferSearchRepository->findExpiringContractTarget($club, $position, $clubBudget);

        if ($selectedPlayer) {
            return ['player' => $selectedPlayer, 'type' => TransferType::PERMANENT_TRANSFER->value];
        }

        $selectedPlayer = $this->transferSearchRepository->findListedPlayer(
            $club,
            TransferType::PERMANENT_TRANSFER,
            $position,
            $clubBudget
        );

        if ($selectedPlayer) {
            return ['player' => $selectedPlayer, 'type' => TransferType::PERMANENT_TRANSFER->value];
        }

        $selectedPlayer = $this->transferSearchRepository->findUpgradeTargetByPosition(
            $club,
            $position,
            $clubBudget
        );

        return $selectedPlayer ? ['player' => $selectedPlayer, 'type' => TransferType::PERMANENT_TRANSFER->value] : null;
    }

    private function executeTransfer(Club $club, array $playerSelection, bool $urgentTransfer): void
    {
        try {
            DB::beginTransaction();

            $this->transferWorkflow->makeAutomaticTransferWithFinancialDetails(
                $playerSelection['player'],
                $club,
                $playerSelection['type'],
                $urgentTransfer
            );

            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            // @todo log exception
            report($exception);
        }
    }
}
