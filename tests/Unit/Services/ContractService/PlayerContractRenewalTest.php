<?php

namespace Tests\Unit\Services\ContractService;

use App\Services\ContractService\Domain\PlayerContractContext;
use App\Services\ContractService\Domain\PlayerContractPerformance;
use App\Services\ContractService\Domain\PlayerContractRenewal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class PlayerContractRenewalTest extends TestCase
{
    #[Test]
    public function salary_expectations_follow_the_market_and_resist_large_cuts_to_existing_pay(): void
    {
        $renewal = new PlayerContractRenewal;
        $context = new PlayerContractContext(2000, 26, 10, 10, 10);

        $this->assertSame(2000, $renewal->requiredSalary($context, ['salary' => 1000], [], 36));
        $this->assertSame(2850, $renewal->requiredSalary($context, ['salary' => 3000], [], 36));
    }

    #[Test]
    public function speculative_bonuses_cannot_replace_guaranteed_weekly_salary(): void
    {
        $renewal = new PlayerContractRenewal;
        $context = new PlayerContractContext(2000, 26, 10, 10, 10);

        $this->assertSame(2000, $renewal->requiredSalary($context, ['salary' => 1000], ['goal' => 1000000, 'league' => 1000000], 36));
        $this->assertSame(1500, $renewal->requiredSalary($context, ['salary' => 1000], ['appearance' => 1000000, 'signing_fee' => 1000000], 36));
    }

    #[Test]
    public function a_signing_fee_is_spread_across_the_offered_term(): void
    {
        $renewal = new PlayerContractRenewal;
        $context = new PlayerContractContext(2000, 26, 10, 10, 10);

        $this->assertSame(1900, $renewal->requiredSalary($context, ['salary' => 1000], ['signing_fee' => 15600], 36));
        $this->assertSame(1700, $renewal->requiredSalary($context, ['salary' => 1000], ['signing_fee' => 15600], 12));
    }

    #[Test]
    public function recent_form_and_regular_minutes_raise_expectations_and_make_event_bonuses_credible(): void
    {
        $performance = new PlayerContractPerformance(5, array_fill(0, 5, ['minutes_played' => 90, 'rating' => 8.5, 'goals' => 1, 'assists' => 0, 'clean_sheet' => false]));
        $context = new PlayerContractContext(2000, 26, 10, 10, 10, $performance);
        $renewal = new PlayerContractRenewal;

        $this->assertSame(2300, $renewal->requiredSalary($context, ['salary' => 1000], [], 36));
        $this->assertSame(2227, $renewal->requiredSalary($context, ['salary' => 1000], ['goal' => 100], 36));
    }

    #[Test]
    #[TestWith([22, 60, 2240])]
    #[TestWith([34, 60, 1840])]
    #[TestWith([26, 6, 2040])]
    public function term_length_affects_young_players_flexibility_and_older_players_security(int $age, int $months, int $expected): void
    {
        $context = new PlayerContractContext(2000, $age, 10, 10, 10);

        $this->assertSame($expected, (new PlayerContractRenewal)->requiredSalary($context, ['salary' => 1000], [], $months));
    }

    #[Test]
    public function ambition_increases_demands_and_loyalty_can_moderate_them(): void
    {
        $renewal = new PlayerContractRenewal;

        $this->assertSame(2200, $renewal->requiredSalary(new PlayerContractContext(2000, 26, 14, 10, 10), ['salary' => 1000], [], 36));
        $this->assertSame(2100, $renewal->requiredSalary(new PlayerContractContext(2000, 26, 14, 10, 20), ['salary' => 1000], [], 36));
    }

    #[Test]
    public function small_form_samples_are_damped_and_existing_bonus_expectations_are_capped(): void
    {
        $performance = new PlayerContractPerformance(1, [['minutes_played' => 90, 'rating' => 1, 'goals' => 20]]);
        $renewal = new PlayerContractRenewal;

        $this->assertSame(-0.03, $performance->formAdjustment());
        $this->assertSame(1188, $renewal->requiredSalary(new PlayerContractContext(100, 26, 10, 10, 10, $performance),
            ['salary' => 1000, 'goal' => 1000000], [], 36));
    }

    #[Test]
    public function loyalty_and_playing_time_influence_willingness_separately_from_salary(): void
    {
        $this->assertFalse((new PlayerContractContext(2000, 26, 15, 10, 10))->isWillingToRenew());
        $this->assertTrue((new PlayerContractContext(2000, 26, 15, 10, 18))->isWillingToRenew());
        $this->assertFalse((new PlayerContractContext(2000, 26, 14, 10, 10, new PlayerContractPerformance(5)))->isWillingToRenew());
        $this->assertSame(0.0, (new PlayerContractPerformance)->formAdjustment());
        $this->assertNull((new PlayerContractPerformance)->playingShare());
    }
}
