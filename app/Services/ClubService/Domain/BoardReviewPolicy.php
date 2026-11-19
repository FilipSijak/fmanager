<?php

namespace App\Services\ClubService\Domain;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class BoardReviewPolicy
{
    private const EARLY_SEASON_ALLOWANCE = 4;

    public function isDue(CarbonInterface $start, CarbonInterface $end, CarbonInterface $date): bool
    {
        if ($end->lessThanOrEqualTo($start) || $date->lessThan($start) || $date->greaterThan($end)) {
            return false;
        }

        if ($date->isSameDay($end)) {
            return true;
        }

        for ($months = 2; ; $months += 2) {
            $candidate = CarbonImmutable::instance($start)->addMonthsNoOverflow($months);
            if ($candidate->greaterThan($date)) {
                return false;
            }
            if ($candidate->isSameDay($date)) {
                return true;
            }
        }
    }

    public function effectiveTolerance(int $baseTolerance, CarbonInterface $start, CarbonInterface $end, CarbonInterface $date): int
    {
        if ($end->lessThanOrEqualTo($start) || $date->greaterThanOrEqualTo($end)) {
            return $baseTolerance;
        }

        $elapsed = max(0.0, (float) $start->diffInDays($date));
        $duration = (float) $start->diffInDays($end);
        $extra = (int) ceil(self::EARLY_SEASON_ALLOWANCE * max(0.0, 1 - $elapsed / $duration));

        return $baseTolerance + $extra;
    }
}
