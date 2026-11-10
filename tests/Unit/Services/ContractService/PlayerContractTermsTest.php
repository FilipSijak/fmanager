<?php

namespace Tests\Unit\Services\ContractService;

use App\Services\ContractService\Domain\PlayerContractTerms;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class PlayerContractTermsTest extends TestCase
{
    #[Test]
    #[TestWith([10, 333, 50])]
    #[TestWith([11, 733, 110])]
    #[TestWith([100, 33333, 5000])]
    #[TestWith([200, 133333, 20000])]
    public function salary_and_bonuses_use_the_matching_potential_tier(int $potential, int $salary, int $appearance): void
    {
        $terms = (new PlayerContractTerms)->forPlayer($potential, 100, 'CB', true);

        $this->assertSame($salary, $terms['salary']);
        $this->assertSame($appearance, $terms['appearance']);
        $this->assertSame(0, $terms['clean_sheet']);
    }

    #[Test]
    public function goalkeeper_bonuses_and_free_agent_signing_fees_are_preserved(): void
    {
        $generator = new PlayerContractTerms;
        $contracted = $generator->forPlayer(100, 100, 'GK', true);
        $free = $generator->forPlayer(100, 100, 'GK', false);

        $this->assertSame(5000, $contracted['clean_sheet']);
        $this->assertSame(43333, $contracted['transfer_fee']);
        $this->assertSame(216665, $free['transfer_fee']);
        $this->assertSame(133332, $contracted['league']);
    }

    #[Test]
    public function agreed_contracts_preserve_existing_dates_and_use_the_game_date_for_new_contracts(): void
    {
        $generator = new PlayerContractTerms;
        $offer = ['salary' => 1200, 'appearance' => 50, 'transfer_fee' => 10000];
        $new = $generator->agreed($offer, '2024-02-29');
        $existing = $generator->agreed($offer, '2026-08-31', '2025-07-01', '2028-06-30');

        $this->assertSame('2024-02-29', $new['contract_start']);
        $this->assertSame('2027-02-28', $new['contract_end']);
        $this->assertSame(10000, $new['signing_fee']);
        $this->assertSame('2025-07-01', $existing['contract_start']);
        $this->assertSame('2028-06-30', $existing['contract_end']);
        $this->assertSame(1200, $existing['salary']);
    }
}
