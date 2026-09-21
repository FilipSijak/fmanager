<?php

namespace App\Services\StadiumService\Domain;

use App\Models\Instance;
use App\Models\StadiumStand;
use App\Models\StadiumStandConstruction;
use App\Repositories\StadiumRepository;
use App\StadiumStandStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CompleteStandConstruction
{
    public function __construct(
        private readonly StadiumRepository $stadiumRepository,
        private readonly StadiumCapacityManager $stadiumCapacityManager,
    ) {}

    public function handle(Instance $instance, CarbonImmutable $asOf): int
    {
        $completed = 0;
        $affectedStadiumIds = [];

        StadiumStandConstruction::query()->where('instance_id', $instance->id)->whereNull('completed_at')
            ->whereDate('completes_at', '<=', $asOf->toDateString())->get()
            ->each(function (StadiumStandConstruction $construction) use (&$completed, &$affectedStadiumIds, $asOf): void {
                DB::transaction(function () use ($construction, &$completed, &$affectedStadiumIds, $asOf): void {
                    $lockedConstruction = StadiumStandConstruction::query()->whereKey($construction->id)->lockForUpdate()->first();
                    if ($lockedConstruction === null) {
                        return;
                    }

                    $affectedStadiumIds[$lockedConstruction->stadium_id] = true;
                    $stand = StadiumStand::query()->whereKey($lockedConstruction->stadium_stand_id)->first();
                    if ($stand !== null) {
                        $stand->forceFill(['status' => StadiumStandStatus::ACTIVE])->saveQuietly();
                    }

                    $lockedConstruction->update(['completed_at' => $asOf->toDateString()]);
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
