<?php

namespace Tests\Unit\Domain\PlayerDevelopment;

use App\Domain\PlayerDevelopment\AgePotentialCurve;
use InvalidArgumentException;
use Tests\TestCase;

class AgePotentialCurveTest extends TestCase
{
    public function test_it_uses_the_first_bracket_before_the_first_age(): void
    {
        $curve = new AgePotentialCurve([
            16 => 0.85,
            24 => 1.0,
        ]);

        $this->assertSame(0.85, $curve->multiplierFor(15));
        $this->assertSame(0.85, $curve->multiplierFor(16));
        $this->assertSame(1.0, $curve->multiplierFor(24));
        $this->assertSame(1.0, $curve->multiplierFor(42));
    }

    public function test_it_calculates_potential_from_the_selected_multiplier(): void
    {
        $curve = new AgePotentialCurve([
            16 => 0.85,
            24 => 1.0,
        ]);

        $this->assertSame(170.0, $curve->potentialFor(200, 15));
        $this->assertSame(200.0, $curve->potentialFor(200, 24));
    }

    public function test_it_rejects_empty_curves(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AgePotentialCurve([]);
    }

    public function test_it_rejects_unsorted_age_brackets(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AgePotentialCurve([
            24 => 1.0,
            16 => 0.85,
        ]);
    }

    public function test_it_rejects_invalid_multipliers(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AgePotentialCurve([
            16 => 1.1,
        ]);
    }
}
