<?php

namespace App\Services\MatchEngineService\Data;

/**
 * A match the engine can play a few minutes at a time, store between requests and change between minutes.
 */
final class MatchState
{
    public const FULL_TIME = 90;

    /**
     * @param  array{home: SideState, away: SideState}  $sides
     * @param  list<array<string, int|string|null>>  $events
     * @param  array{expected_chances: array{home: float, away: float}, expected_goals: array{home: float, away: float}}  $kickOffExpectations
     */
    public function __construct(
        public int $minute,
        public readonly array $sides,
        public array $events,
        public readonly array $kickOffExpectations,
    ) {}

    public function side(string $side): SideState
    {
        return $this->sides[$side];
    }

    public function isFinished(): bool
    {
        return $this->minute >= self::FULL_TIME;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'minute' => $this->minute,
            'sides' => array_map(fn (SideState $side): array => $side->toArray(), $this->sides),
            'events' => $this->events,
            'kick_off_expectations' => $this->kickOffExpectations,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            minute: $data['minute'],
            sides: array_map(SideState::fromArray(...), $data['sides']),
            events: $data['events'],
            kickOffExpectations: $data['kick_off_expectations'],
        );
    }
}
