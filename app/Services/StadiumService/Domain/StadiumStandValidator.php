<?php

namespace App\Services\StadiumService\Domain;

use App\Models\Stadium;
use App\Models\StadiumStand;
use App\Repositories\StadiumRepository;
use App\StadiumStandPosition;
use DomainException;

class StadiumStandValidator
{
    public function __construct(
        private readonly StadiumRepository $stadiumRepository,
    ) {}

    public function validatePosition(Stadium $stadium, StadiumStandPosition $position): void
    {
        if ($this->stadiumRepository->maximumCapacityForStand($stadium->type, $position) === 0) {
            if ($stadium->type->allowsCornerStands() === false && in_array($position, [
                StadiumStandPosition::NORTH_EAST,
                StadiumStandPosition::SOUTH_EAST,
                StadiumStandPosition::SOUTH_WEST,
                StadiumStandPosition::NORTH_WEST,
            ], true)) {
                throw new DomainException('Village and Local stadiums cannot build corner stands.');
            }

            throw new DomainException('This stand position is not available for the stadium type.');
        }
    }

    public function validateCapacity(StadiumStand $stand, int $maximumCapacity): void
    {
        if ($stand->capacity === null) {
            return;
        }

        $this->validateCapacityValue((int) $stand->capacity, $maximumCapacity);
    }

    public function validateCapacityValue(int $capacity, int $maximumCapacity): void
    {
        if ($capacity < 0) {
            throw new DomainException('Stadium stand capacity cannot be negative.');
        }

        if ($capacity % 1000 !== 0) {
            throw new DomainException('Stadium stand capacity must be a multiple of 1,000 seats.');
        }

        if ($capacity > $maximumCapacity) {
            throw new DomainException('Stadium stand capacity exceeds the maximum for its position.');
        }
    }
}
