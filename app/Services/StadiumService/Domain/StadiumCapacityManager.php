<?php

namespace App\Services\StadiumService\Domain;

use App\Models\Instance;
use App\Models\Stadium;
use App\Models\StadiumStand;
use App\Repositories\StadiumRepository;
use App\StadiumStandStatus;
use Illuminate\Database\Eloquent\Collection;

class StadiumCapacityManager
{
    public function __construct(private readonly StadiumRepository $stadiumRepository) {}

    public function recalculate(Stadium $stadium): void
    {
        $stands = $this->stadiumRepository->standsForCapacity($stadium);

        $stadium->forceFill([
            'capacity' => (int) $stands->sum('capacity'),
            'active_capacity' => $this->activeCapacityForStands($stands),
        ])->saveQuietly();
    }

    /** @param Collection<int, StadiumStand> $stands */
    private function activeCapacityForStands(Collection $stands): int
    {
        return (int) $stands->where('status', StadiumStandStatus::ACTIVE)->sum('capacity');
    }

    public function recalculateForInstance(Instance $instance): void
    {
        $this->stadiumRepository->stadiumsWithStandConstructionForInstance($instance)
            ->each(function (Stadium $stadium): void {
                $this->recalculate($stadium);
            });
    }
}
