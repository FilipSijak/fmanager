<?php

namespace App\Services\StadiumService\Domain;

use App\Models\Stadium;
use App\Models\StadiumStand;
use App\Repositories\StadiumRepository;
use App\StadiumStandStatus;
use DomainException;
use Illuminate\Database\Eloquent\Collection;

class StadiumBuildValidator
{
    public function __construct(
        private readonly StadiumRepository $stadiumRepository,
        private readonly StadiumStandValidator $stadiumStandValidator,
    ) {}

    public function validate(Stadium $stadium): void
    {
        $stands = $this->stadiumRepository->standsForValidation($stadium);
        $capacity = (int) $stands->sum('capacity');
        $activeCapacity = $this->activeCapacityForStands($stands);

        foreach ($stands as $stand) {
            if ($stand->position === null) {
                throw new DomainException('Stadium stands must have a position.');
            }

            $this->stadiumStandValidator->validatePosition($stadium, $stand->position);
            $this->stadiumStandValidator->validateCapacity(
                $stand,
                $this->stadiumRepository->maximumCapacityForStand($stadium->type, $stand->position),
            );
        }

        if ($stands->count() > $this->stadiumRepository->maximumStandsForType($stadium->type)) {
            throw new DomainException('A stadium cannot have more stands than its type allows.');
        }

        $this->validateCapacity($stadium, $capacity);

        if ((int) $stadium->capacity !== $capacity) {
            throw new DomainException('Stadium capacity does not match its stands.');
        }

        if ((int) $stadium->active_capacity !== $activeCapacity) {
            throw new DomainException('Stadium active capacity does not match its active stands.');
        }
    }

    public function validateCapacity(Stadium $stadium, int $capacity): void
    {
        if ($capacity < 0) {
            throw new DomainException('Stadium capacity cannot be negative.');
        }

        if ($capacity > $stadium->type->maximumCapacity()) {
            throw new DomainException('Stadium capacity exceeds the maximum for its stadium type.');
        }
    }

    /** @param Collection<int, StadiumStand> $stands */
    private function activeCapacityForStands(Collection $stands): int
    {
        return (int) $stands
            ->where('status', StadiumStandStatus::ACTIVE)
            ->sum('capacity');
    }
}
