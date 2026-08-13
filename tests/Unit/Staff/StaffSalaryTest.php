<?php

namespace Tests\Unit\Staff;

use App\Services\PersonService\GeneratePeople\StaffSalary;
use App\Services\PersonService\PersonConfig\PersonTypes;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class StaffSalaryTest extends TestCase
{
    #[Test]
    public function it_calculates_salary_from_staff_role_and_potential(): void
    {
        $salary = new StaffSalary;

        $this->assertRange($salary, PersonTypes::PHYSIO, 500, 1800, 3000);
        $this->assertRange($salary, PersonTypes::YOUTH_PHYSIO, 500, 1800, 3000);
        $this->assertRange($salary, PersonTypes::SCOUT, 1000, 1500, 2000);
        $this->assertRange($salary, PersonTypes::COACH, 1000, 4800, 8000);
        $this->assertRange($salary, PersonTypes::YOUTH_COACH, 1000, 4800, 8000);
        $this->assertRange($salary, PersonTypes::ASSISTANT_MANAGER, 1500, 6100, 10000);
        $this->assertRange($salary, PersonTypes::MANAGER, 5000, 5359500, 10000000);
    }

    #[Test]
    public function it_rejects_unsupported_staff_roles(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new StaffSalary)->estimatedSalaryForStaffRole(PersonTypes::PLAYER, 100);
    }

    private function assertRange(
        StaffSalary $salary,
        string $role,
        int $minimum,
        int $midpoint,
        int $maximum
    ): void {
        $this->assertSame($minimum, $salary->estimatedSalaryForStaffRole($role, 0));
        $this->assertSame($minimum, $salary->estimatedSalaryForStaffRole($role, 50));
        $this->assertSame($midpoint, $salary->estimatedSalaryForStaffRole($role, 125));
        $this->assertSame($maximum, $salary->estimatedSalaryForStaffRole($role, 190));
        $this->assertSame($maximum, $salary->estimatedSalaryForStaffRole($role, 200));
    }
}
