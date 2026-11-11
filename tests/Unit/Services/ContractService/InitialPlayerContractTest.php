<?php

namespace Tests\Unit\Services\ContractService;

use App\Services\ContractService\Domain\InitialPlayerContract;
use App\Services\ContractService\Domain\PlayerContractTerms;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class InitialPlayerContractTest extends TestCase
{
    #[Test]
    #[TestWith([6, '2027-02-28'])]
    #[TestWith([60, '2031-08-31'])]
    public function initial_dates_use_the_game_date_and_bounded_months(int $months, string $end): void
    {
        $terms = (new PlayerContractTerms)->forPlayer(100, 100, 'CB', false);
        $data = (new InitialPlayerContract)->data($terms, '2026-08-31', $months);

        $this->assertSame('2026-08-31', $data['contract_start']);
        $this->assertSame($end, $data['contract_end']);
        $this->assertSame(33333, $data['salary']);
        $this->assertSame(0, $data['signing_fee']);
    }

    #[Test]
    #[TestWith([5])]
    #[TestWith([61])]
    public function initial_terms_cannot_exceed_the_supported_duration(int $months): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new InitialPlayerContract)->data([], '2026-07-01', $months);
    }
}
