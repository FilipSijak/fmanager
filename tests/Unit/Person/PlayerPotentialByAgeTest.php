<?php

namespace Tests\Unit\Person;

use App\Services\PersonService\Data\PotentialByCategoryData;
use App\Services\PersonService\GeneratePeople\PlayerPotential;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlayerPotentialByAgeTest extends TestCase
{
    #[Test]
    #[DataProvider('potentialByAgeProvider')]
    public function it_calculates_potential_from_max_potential_and_age(int $age, int $expected): void
    {
        $asOfDate = CarbonImmutable::parse('2026-06-16');

        $this->assertSame(
            (float) $expected,
            (new PlayerPotential)->onDate(200, $asOfDate->subYears($age), $asOfDate)
        );
    }

    #[Test]
    public function it_calculates_each_category_potential_from_its_own_curve(): void
    {
        $asOfDate = CarbonImmutable::parse('2026-06-16');
        $maxPotentials = new PotentialByCategoryData(100, 150, 200);

        $currentPotentials = (new PlayerPotential)->potentialByCategoryOnDate(
            $maxPotentials,
            $asOfDate->subYears(38),
            $asOfDate
        );

        $this->assertSame(90, $currentPotentials->technical);
        $this->assertSame(132, $currentPotentials->mental);
        $this->assertSame(124, $currentPotentials->physical);
    }

    #[Test]
    #[DataProvider('categoryPotentialByAgeProvider')]
    public function it_calculates_each_category_curve_at_key_ages(
        int $age,
        int $technical,
        int $mental,
        int $physical
    ): void {
        $asOfDate = CarbonImmutable::parse('2026-06-16');
        $maxPotentials = new PotentialByCategoryData(100, 100, 100);

        $currentPotentials = (new PlayerPotential)->potentialByCategoryOnDate(
            $maxPotentials,
            $asOfDate->subYears($age),
            $asOfDate
        );

        $this->assertSame($technical, $currentPotentials->technical);
        $this->assertSame($mental, $currentPotentials->mental);
        $this->assertSame($physical, $currentPotentials->physical);
    }

    public static function categoryPotentialByAgeProvider(): array
    {
        return [
            '35 years old' => [35, 93, 91, 75],
            '38 years old' => [38, 90, 88, 62],
            '41 years old' => [41, 85, 82, 50],
        ];
    }

    public static function potentialByAgeProvider(): array
    {
        return [
            '16 years old' => [16, 170],
            '18 years old' => [18, 180],
            '21 years old' => [21, 190],
            '24 years old' => [24, 200],
            '29 years old' => [29, 196],
            '30 years old' => [30, 190],
            '32 years old' => [32, 184],
            '33 years old' => [33, 178],
            '35 years old' => [35, 166],
            '38 years old' => [38, 150],
            '41 years old' => [41, 134],
        ];
    }
}
