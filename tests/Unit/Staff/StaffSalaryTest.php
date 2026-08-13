<?php

namespace Tests\Unit\Staff;

use App\Services\PersonService\GeneratePeople\StaffSalary;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class StaffSalaryTest extends TestCase
{
    #[Test]
    public function it_calculates_salary_from_staff_potential(): void
    {
        $salary = new StaffSalary;

        $this->assertSame(500, $salary->forPotential(0));
        $this->assertSame(500, $salary->forPotential(50));
        $this->assertSame(4300, $salary->forPotential(100));
        $this->assertSame(8200, $salary->forPotential(150));
        $this->assertSame(12000, $salary->forPotential(200));
        $this->assertSame(12000, $salary->forPotential(220));
    }
}
