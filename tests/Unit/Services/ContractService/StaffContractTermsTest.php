<?php

namespace Tests\Unit\Services\ContractService;

use App\Services\ContractService\Domain\StaffContractTerms;
use App\Services\ContractService\Domain\StaffSalary;
use App\Services\PersonService\PersonConfig\PersonTypes;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class StaffContractTermsTest extends TestCase
{
    #[Test]
    #[TestWith([6, '2027-02-28'])]
    #[TestWith([60, '2031-08-31'])]
    public function staff_dates_and_salary_are_generated_in_the_contract_domain(int $months, string $end): void
    {
        $terms = (new StaffContractTerms(new StaffSalary))->data(PersonTypes::SCOUT, 50, '2026-08-31', $months);

        $this->assertSame('2026-08-31', $terms['contract_start']);
        $this->assertSame($end, $terms['contract_end']);
        $this->assertSame(1000, $terms['salary']);
        $this->assertNull($terms['signing_fee']);
    }

    #[Test]
    #[TestWith([5])]
    #[TestWith([61])]
    public function staff_contracts_reject_invalid_lengths(int $months): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new StaffContractTerms(new StaffSalary))->data(PersonTypes::SCOUT, 50, '2026-07-01', $months);
    }
}
