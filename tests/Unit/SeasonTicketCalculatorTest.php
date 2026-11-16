<?php

namespace Tests\Unit;

use App\Services\SeasonTicketService\Domain\SeasonTicketCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SeasonTicketCalculatorTest extends TestCase
{
    #[Test]
    #[DataProvider('refundLimits')]
    public function construction_refunds_only_cover_paid_admission_lost_to_construction(int $holders, int $capacity, int $constructionCapacity, int $price, int $quantity, int $amount): void
    {
        $calculator = new SeasonTicketCalculator;

        $refund = $calculator->constructionRefund($holders, $capacity, $constructionCapacity, $price);

        $this->assertSame($quantity, $refund['season_ticket_refund_quantity']);
        $this->assertSame($amount, $refund['season_ticket_refund_amount']);
    }

    /** @return array<string, array{int, int, int, int, int, int}> */
    public static function refundLimits(): array
    {
        return [
            'no season tickets' => [0, 0, 1000, 100, 0, 0],
            'closure unrelated to construction' => [400, 0, 0, 100, 0, 0],
            'only the construction loss is compensated' => [400, 100, 200, 100, 200, 20000],
            'free passes have no money to refund' => [400, 200, 800, 0, 200, 0],
        ];
    }
}
