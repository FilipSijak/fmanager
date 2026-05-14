<?php

namespace Tests\Unit\Helpers;

use App\Helpers\DisplayHelpers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DisplayHelpersTest extends TestCase
{
    #[Test]
    #[DataProvider('roundAmountProvider')]
    public function it_rounds_amounts_by_value_band(int|float $amount, int $expected): void
    {
        $this->assertSame($expected, DisplayHelpers::roundAmounts($amount));
    }

    public static function roundAmountProvider(): array
    {
        return [
            'below 100k rounds to nearest thousand' => [99499, 99000],
            'below 100k rounds up to nearest thousand' => [99500, 100000],
            '100k to below 1m rounds to nearest ten thousand' => [104999, 100000],
            '100k to below 1m rounds up to nearest ten thousand' => [105000, 110000],
            '1m to below 10m rounds to nearest hundred thousand' => [1499999, 1500000],
            '1m to below 10m rounds up to nearest hundred thousand' => [1550000, 1600000],
            '10m and above rounds to nearest million' => [10499999, 10000000],
            '10m and above rounds up to nearest million' => [10500000, 11000000],
            'float amounts are rounded using the same bands' => [999999.5, 1000000],
        ];
    }
}
