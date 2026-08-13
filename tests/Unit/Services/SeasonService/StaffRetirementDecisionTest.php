<?php

namespace Tests\Unit\Services\SeasonService;

use App\Services\SeasonService\StaffRetirementDecision;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class StaffRetirementDecisionTest extends TestCase
{
    #[Test]
    public function age_chance_increases_and_is_guaranteed_at_75(): void
    {
        $decision = new StaffRetirementDecision;

        $this->assertSame(0, $decision->ageChanceBasisPoints(59));
        $this->assertSame(625, $decision->ageChanceBasisPoints(60));
        $this->assertSame(5000, $decision->ageChanceBasisPoints(67));
        $this->assertSame(9375, $decision->ageChanceBasisPoints(74));
        $this->assertSame(10000, $decision->ageChanceBasisPoints(75));
        $this->assertSame(10000, $decision->ageChanceBasisPoints(80));
    }
}
