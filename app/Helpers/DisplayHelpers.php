<?php

namespace App\Helpers;

class DisplayHelpers
{
    public static function roundAmounts(int|float $amount): int
    {
        if ($amount < 100000) {
            return (int) round($amount, -3);
        }

        if ($amount < 1000000) {
            return (int) round($amount, -4);
        }

        if ($amount < 10000000) {
            return (int) round($amount, -5);
        }

        return (int) round($amount, -6);
    }
}
