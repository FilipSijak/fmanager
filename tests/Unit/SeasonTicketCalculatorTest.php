<?php

namespace Tests\Unit;

use App\Services\CommercialService\Domain\SeasonTicketCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SeasonTicketCalculatorTest extends TestCase
{
    #[Test]
    #[DataProvider('discountedOffers')]
    public function season_passes_cost_seventy_percent_of_regular_admission(int $leaguePrice, int $homeGames, int $ticketPrice, int $amount): void
    {
        $offer = (new SeasonTicketCalculator)->offer(1000, $leaguePrice, $homeGames);

        $this->assertSame($leaguePrice, $offer['league_ticket_price']);
        $this->assertSame($ticketPrice, $offer['ticket_price']);
        $this->assertSame($amount, $offer['amount']);
    }

    /** @return array<string, array{int, int, int, int}> */
    public static function discountedOffers(): array
    {
        return [
            'full season' => [100, 19, 1330, 532000],
            'remaining matches' => [100, 2, 140, 56000],
            'round once for the package' => [11, 3, 23, 9200],
            'free admission' => [0, 19, 0, 0],
        ];
    }

    #[Test]
    #[DataProvider('refundLimits')]
    public function construction_refunds_only_cover_paid_admission_lost_to_construction(int $holders, int $capacity, int $constructionCapacity, float $price, int $quantity, int $amount): void
    {
        $calculator = new SeasonTicketCalculator;

        $refund = $calculator->constructionRefund($holders, $capacity, $constructionCapacity, $price);

        $this->assertSame($quantity, $refund['season_ticket_refund_quantity']);
        $this->assertSame($amount, $refund['season_ticket_refund_amount']);
    }

    /** @return array<string, array{int, int, int, float|int, int, int}> */
    public static function refundLimits(): array
    {
        return [
            'no season tickets' => [0, 0, 1000, 100, 0, 0],
            'closure unrelated to construction' => [400, 0, 0, 100, 0, 0],
            'only the construction loss is compensated' => [400, 100, 200, 100, 200, 20000],
            'discounted passes refund the paid rate' => [400, 200, 800, 70, 200, 14000],
            'fractional paid rate rounds the total refund' => [400, 200, 800, 7 / 3, 200, 467],
            'free passes have no money to refund' => [400, 200, 800, 0, 200, 0],
        ];
    }
}
