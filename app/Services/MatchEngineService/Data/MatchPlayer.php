<?php

namespace App\Services\MatchEngineService\Data;

/**
 * A matchday squad player's ratings for what the engine rates teams on and credits to individual players, on the
 * 1-20 attribute scale.
 */
final class MatchPlayer
{
    public function __construct(
        public readonly int $playerId,
        /** Position played: the lineup slot for starters, the natural position for substitutes until they come on. */
        public readonly string $position,
        public readonly float $shooting,
        public readonly float $creating,
        public readonly float $passing,
        public readonly float $tackling,
        public readonly float $intercepting,
        public readonly float $dribbling,
        public readonly float $aggression,
        public readonly float $decisions,
        public readonly float $goalkeeping,
        public readonly float $playmaking = 10.0,
        public readonly float $defending = 10.0,
    ) {}

    /** The same player moved to another position, as when coming on as a substitute. */
    public function playingAs(string $position): self
    {
        return new self(...[...$this->toArray(), 'position' => $position]);
    }

    /** @return array<string, int|float|string> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /** @param array<string, int|float|string> $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
