<?php

namespace App\Services\TransferService;

use App\Models\Club;
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

    public function playerDeficitTransferAttempt(Club $club, Collection $deficitPositions, int $clubBudget)
    {
        foreach ($deficitPositions as $position => $deficitNumber) {
            $urgentTransfer = $this->isUrgentTransfer($position, $deficitNumber);
            $playerSelection = $this->findSuitablePlayer($club, $position, $clubBudget);

            if (!$playerSelection) {
                continue;
            }

            $this->executeTransfer($club, $playerSelection, $urgentTransfer, $clubBudget);
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

    private function executeTransfer(Club $club, array $playerSelection, bool $urgentTransfer, int &$clubBudget): void
    {
        try {
            DB::beginTransaction();

            $transfer = $this->transferRepository->makeAutomaticTransferWithFinancialDetails(
                $playerSelection['player'],
                $club,
                $playerSelection['type'],
                $urgentTransfer
            );
            $clubBudget -= $transfer->amount;

            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            // Consider logging the exception
            report($exception);
        }
    }
}
