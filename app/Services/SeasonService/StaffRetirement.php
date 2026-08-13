<?php

namespace App\Services\SeasonService;

use App\Models\Instance;
use App\Repositories\StaffRepository;
use Carbon\CarbonImmutable;

class StaffRetirement
{
    private const MINIMUM_RETIREMENT_AGE = 60;

    public function __construct(
        private readonly StaffRepository $staffRepository,
        private readonly StaffRetirementDecision $retirementDecision
    ) {}

    public function retireEligibleStaff(Instance $instance): int
    {
        $asOfDate = CarbonImmutable::parse($instance->instance_date)->startOfDay();
        $cutoffDate = $asOfDate->subYears(self::MINIMUM_RETIREMENT_AGE);
        $retiredCount = 0;

        $staffMembers = $this->staffRepository->staffEligibleForRetirement(
            (int) $instance->id,
            $cutoffDate->toDateString()
        );

        foreach ($staffMembers as $staff) {
            if (! $this->retirementDecision->shouldRetire($staff, $asOfDate)) {
                continue;
            }

            if ($this->staffRepository->retireStaff($staff)) {
                $retiredCount++;
            }
        }

        return $retiredCount;
    }
}
