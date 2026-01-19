<?php

if (! function_exists('roundAmount')) {
    function roundAmount($amount): int {
        $amountSize = strlen((string) $amount);

        if ($amountSize <= 6) {
            return round($amount, -3);
        }

        return round($amount, -6);
    }
}
