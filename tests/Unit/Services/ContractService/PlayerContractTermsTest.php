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
}
