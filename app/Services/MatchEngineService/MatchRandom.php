<?php

namespace App\Services\MatchEngineService;

use Random\Randomizer;

/**
 * The random draws a match is made of, on one seedable randomizer so a match can be replayed in tests.
 */
final class MatchRandom
{
    public function __construct(
        private readonly Randomizer $randomizer,
    ) {}

    /**
     * A Poisson-distributed count around the expected value (Knuth's method).
     */
    public function poisson(float $expected): int
    {
        $threshold = exp(-$expected);
        $count = 0;
        $probability = $this->randomizer->nextFloat();

        while ($probability > $threshold) {
            $count++;
            $probability *= $this->randomizer->nextFloat();
        }

        return $count;
    }

    /** How many of the given attempts succeed at the given probability. */
    public function successes(int $attempts, float $probability): int
    {
        $successes = 0;

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            if ($this->happens($probability)) {
                $successes++;
            }
        }

        return $successes;
    }

    public function happens(float $probability): bool
    {
        return $this->randomizer->nextFloat() < $probability;
    }

    /**
     * Picks a key with probability proportional to its weight, or null when no key has any weight.
     *
     * @template TKey of array-key
     *
     * @param  array<TKey, float>  $weights
     * @return TKey|null
     */
    public function pick(array $weights): int|string|null
    {
        $total = array_sum($weights);

        if ($total <= 0) {
            return null;
        }

        $target = $this->randomizer->nextFloat() * $total;

        foreach ($weights as $key => $weight) {
            $target -= $weight;

            if ($target < 0) {
                return $key;
            }
        }

        return array_key_last(array_filter($weights, fn (float $weight): bool => $weight > 0));
    }

    public function minute(int $matchMinutes): int
    {
        return $this->randomizer->getInt(1, $matchMinutes);
    }
}
