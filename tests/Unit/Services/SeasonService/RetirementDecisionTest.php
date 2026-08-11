<?php

namespace Tests\Unit\Services\SeasonService;

use App\Services\SeasonService\RetirementDecision;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RetirementDecisionTest extends TestCase
{
    #[Test]
    public function age_chance_increases_and_is_guaranteed_at_45(): void
    {
        $decision = new RetirementDecision;

        $this->assertSame(0, $decision->ageChanceBasisPoints(31));
        $this->assertSame(714, $decision->ageChanceBasisPoints(32));
        $this->assertSame(5000, $decision->ageChanceBasisPoints(38));
        $this->assertSame(9286, $decision->ageChanceBasisPoints(44));
        $this->assertSame(10000, $decision->ageChanceBasisPoints(45));
        $this->assertSame(10000, $decision->ageChanceBasisPoints(50));
    }
}
