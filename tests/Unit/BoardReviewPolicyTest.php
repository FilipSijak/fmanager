<?php

namespace Tests\Unit;

use App\Services\ClubService\Domain\BoardReviewPolicy;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class BoardReviewPolicyTest extends TestCase
{
    #[Test]
    #[TestWith(['2030-07-15', false])]
    #[TestWith(['2030-08-15', false])]
    #[TestWith(['2030-09-15', false])]
    #[TestWith(['2030-10-15', true])]
    #[TestWith(['2030-10-16', false])]
    #[TestWith(['2030-11-15', false])]
    #[TestWith(['2030-12-15', true])]
    #[TestWith(['2031-02-15', true])]
    #[TestWith(['2031-04-15', true])]
    #[TestWith(['2031-06-15', true])]
    #[TestWith(['2031-06-16', false])]
    public function reviews_are_due_every_two_months_from_season_start_and_at_season_end(string $date, bool $due): void
    {
        $this->assertSame($due, (new BoardReviewPolicy)->isDue(
            CarbonImmutable::parse('2030-08-15'), CarbonImmutable::parse('2031-06-15'), CarbonImmutable::parse($date),
        ));
    }

    #[Test]
    #[TestWith(['2030-10-15', 8])]
    #[TestWith(['2030-12-15', 7])]
    #[TestWith(['2031-02-15', 6])]
    #[TestWith(['2031-04-15', 5])]
    #[TestWith(['2031-06-15', 4])]
    #[TestWith(['2031-07-15', 4])]
    #[TestWith(['2030-07-15', 8])]
    public function extra_tolerance_decreases_to_the_base_tolerance(string $date, int $tolerance): void
    {
        $this->assertSame($tolerance, (new BoardReviewPolicy)->effectiveTolerance(
            4, CarbonImmutable::parse('2030-08-15'), CarbonImmutable::parse('2031-06-15'), CarbonImmutable::parse($date),
        ));
    }

    #[Test]
    #[TestWith(['2030-10-31'])]
    #[TestWith(['2030-12-31'])]
    #[TestWith(['2031-02-28'])]
    #[TestWith(['2031-04-30'])]
    #[TestWith(['2031-05-31'])]
    public function month_end_anchors_do_not_drift_and_the_final_review_is_not_missed(string $date): void
    {
        $this->assertTrue((new BoardReviewPolicy)->isDue(
            CarbonImmutable::parse('2030-08-31'), CarbonImmutable::parse('2031-05-31'), CarbonImmutable::parse($date),
        ));
    }

    #[Test]
    public function invalid_season_dates_do_not_schedule_reviews_or_inflate_tolerance(): void
    {
        $date = CarbonImmutable::parse('2030-08-15');
        $policy = new BoardReviewPolicy;

        $this->assertFalse($policy->isDue($date, $date, $date));
        $this->assertSame(4, $policy->effectiveTolerance(4, $date, $date, $date));
    }
}
