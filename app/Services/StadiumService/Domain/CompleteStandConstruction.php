<?php

namespace App\Services\StadiumService\Domain;

use App\Models\Instance;
use App\Models\StadiumStandConstruction;
use App\Repositories\StadiumConstructionRepository;
use App\Repositories\StadiumRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CompleteStandConstruction
{
    public function __construct(
        private readonly StadiumRepository $stadiumRepository,
        private readonly StadiumConstructionRepository $stadiumConstructionRepository,
        private readonly StadiumCapacityManager $stadiumCapacityManager,
    ) {}

    public function handle(Instance $instance, CarbonImmutable $asOf): int
    {
        $completed = 0;
        $affectedStadiumIds = [];

        $this->stadiumConstructionRepository->dueForInstance($instance, $asOf)
            ->each(function (StadiumStandConstruction $construction) use (&$completed, &$affectedStadiumIds, $asOf): void {
                DB::transaction(function () use ($construction, &$completed, &$affectedStadiumIds, $asOf): void {
                    $lockedConstruction = $this->stadiumConstructionRepository->lockConstruction($construction->id);
                    if ($lockedConstruction === null) {
                        return;
                    }

                    $affectedStadiumIds[$lockedConstruction->stadium_id] = true;
                    $this->stadiumConstructionRepository->activateStand($lockedConstruction->stadium_stand_id);

                    $this->stadiumConstructionRepository->markCompleted($lockedConstruction, $asOf);
                    $completed++;
                });
            });

        foreach (array_keys($affectedStadiumIds) as $stadiumId) {
            $stadium = $this->stadiumRepository->stadiumById((int) $stadiumId);
            if ($stadium !== null) {
                $this->stadiumCapacityManager->recalculate($stadium);
            }
        }

        return $completed;
    }
}
