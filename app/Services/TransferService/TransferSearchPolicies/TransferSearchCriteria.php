<?php

namespace App\Services\TransferService\TransferSearchPolicies;

use App\Models\Club;
use App\Services\TransferService\TransferType;
use Carbon\CarbonImmutable;

final class TransferSearchCriteria
{
    private const int RECENT_OFFER_YEARS = 2;

    private const int CONTRACT_EXPIRY_MONTHS = 6;

    private const int CLUB_RANK_POTENTIAL_MULTIPLIER = 10;

    private const int POTENTIAL_TOLERANCE = 20;

    public function recentOfferCutoff(string $instanceDate): CarbonImmutable
    {
        return CarbonImmutable::parse($instanceDate)->subYears(self::RECENT_OFFER_YEARS);
    }

    public function minimumPotentialFor(Club $club): int
    {
        return $club->rank * self::CLUB_RANK_POTENTIAL_MULTIPLIER - self::POTENTIAL_TOLERANCE;
    }

    /** @return array{0: string, 1: string} */
    public function expiringContractWindow(string $instanceDate): array
    {
        $start = CarbonImmutable::parse($instanceDate);

        return [
            $start->toDateString(),
            $start->addMonths(self::CONTRACT_EXPIRY_MONTHS)->toDateString(),
        ];
    }

    public function minimumUpgradePotential(int $currentPotential): int
    {
        return $currentPotential + 1;
    }

    public function minimumLoanPotential(?float $averagePotential): float
    {
        return $averagePotential ?? 0.0;
    }

    public function requiresUpgrade(TransferType $transferType): bool
    {
        return $transferType === TransferType::PERMANENT_TRANSFER;
    }
}
