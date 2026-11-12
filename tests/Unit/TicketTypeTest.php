<?php

namespace Tests\Unit;

use App\Services\CommercialService\Domain\TicketPriceCalculator;
use App\Services\CommercialService\Domain\TicketType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TicketTypeTest extends TestCase
{
    #[Test]
    public function league_pricing_keeps_the_existing_formula_and_other_types_apply_the_requested_adjustments(): void
    {
        $calculator = new TicketPriceCalculator;

        $this->assertSame(55, $calculator->calculate(10, 5000, 50));
        $this->assertSame(['league' => 100, 'cup' => 80, 'continental' => 120], $calculator->prices(100));
        $this->assertSame(['league' => 42, 'cup' => 34, 'continental' => 50], $calculator->prices(42));
        $this->assertSame(['league' => 0, 'cup' => 0, 'continental' => 0], $calculator->prices(0));
    }

    #[Test]
    #[DataProvider('competitions')]
    public function competition_scope_takes_priority_over_format(string $format, string $scope, TicketType $expected): void
    {
        $this->assertSame($expected, TicketType::forCompetition($format, $scope));
    }

    /** @return array<string, array{string, string, TicketType}> */
    public static function competitions(): array
    {
        return ['league' => ['league', 'domestic', TicketType::LEAGUE],
            'cup' => ['tournament', 'domestic', TicketType::CUP],
            'continental cup' => ['tournament', 'continental', TicketType::CONTINENTAL],
            'continental league' => ['league', 'continental', TicketType::CONTINENTAL]];
    }
}
