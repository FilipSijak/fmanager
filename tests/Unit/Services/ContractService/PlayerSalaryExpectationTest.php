<?php

namespace Tests\Unit\Services\ContractService;

use App\Services\ContractService\Domain\PlayerSalaryExpectation;
use App\Services\PersonService\PersonConfig\Player\PlayerFields;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PlayerSalaryExpectationTest extends TestCase
{
    #[Test]
    public function ability_uses_current_skills_and_goalkeeping_for_goalkeepers(): void
    {
        $attributes = array_fill_keys(array_merge(PlayerFields::TECHNICAL_FIELDS, PlayerFields::MENTAL_FIELDS, PlayerFields::PHYSICAL_FIELDS), 10);
        $attributes += array_fill_keys(PlayerFields::GOALKEEPING_FIELDS, 20);
        $attributes['potential'] = 200;
        $calculator = new PlayerSalaryExpectation;

        $this->assertSame(100, $calculator->ability($attributes, 'CB'));
        $this->assertSame(140, $calculator->ability($attributes, 'GK'));
    }

    #[Test]
    public function ability_and_reputation_set_the_baseline_with_bounded_age_and_potential_adjustments(): void
    {
        $calculator = new PlayerSalaryExpectation;

        $this->assertSame(33333, $calculator->salary(100, 100));
        $this->assertSame(16667, $calculator->salary(100, 50));
        $this->assertSame(13800, $calculator->salary(60, 100, 20, 160));
        $this->assertSame(28333, $calculator->salary(100, 100, 35, 200));
        $this->assertSame(33333, $calculator->salary(100, 0));
    }
}
