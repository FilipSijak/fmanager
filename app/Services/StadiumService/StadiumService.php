<?php

namespace App\Services\StadiumService;

use App\Models\Stadium;
use App\Models\StadiumCommercialVenue;
use App\Models\StadiumStand;
use App\Services\CommercialService\CommercialVenueSize;
use App\StadiumStandStatus;
use DomainException;
use Illuminate\Support\Facades\DB;

class StadiumService
{
    public function typeForCapacity(int $capacity): StadiumType
    {
        return StadiumType::fromCapacity($capacity);
    }

    public function commercialLimitForType(StadiumType $type): int
    {
        return $type->commercialLimit();
    }

    public function buildCommercialVenue(Stadium $stadium, int $categoryId, CommercialVenueSize $size): StadiumCommercialVenue
    {
        return DB::transaction(function () use ($stadium, $categoryId, $size): StadiumCommercialVenue {
            $lockedStadium = Stadium::query()->whereKey($stadium->id)->lockForUpdate()->firstOrFail();
            $stadiumType = $lockedStadium->type;

            $categoryIsAvailable = DB::table('base_commercial_category_stadium_type')
                ->where('category_id', $categoryId)
                ->where('stadium_type', $stadiumType->value)
                ->exists();

            if (! $categoryIsAvailable) {
                throw new DomainException('This commercial category is not available for the stadium type.');
            }

            $categoryAlreadyBuilt = StadiumCommercialVenue::query()
                ->where('instance_id', $lockedStadium->instance_id)
                ->where('stadium_id', $lockedStadium->id)
                ->where('category_id', $categoryId)
                ->exists();

            if ($categoryAlreadyBuilt) {
                throw new DomainException('This commercial venue category has already been built at the stadium.');
            }

            $venueCount = StadiumCommercialVenue::query()
                ->where('instance_id', $lockedStadium->instance_id)
                ->where('stadium_id', $lockedStadium->id)
                ->count();

            if ($venueCount >= $lockedStadium->commercial_limit) {
                throw new DomainException('The stadium has reached its commercial venue limit.');
            }

            return StadiumCommercialVenue::query()->create([
                'instance_id' => $lockedStadium->instance_id,
                'stadium_id' => $lockedStadium->id,
                'category_id' => $categoryId,
                'size' => $size,
            ]);
        });
    }

    public function recalculateCapacities(Stadium $stadium): void
    {
        $stands = StadiumStand::query()->whereBelongsTo($stadium);

        $stadium->forceFill([
            'capacity' => (int) (clone $stands)->sum('capacity'),
            'active_capacity' => (int) $stands
                ->where('status', StadiumStandStatus::ACTIVE->value)
                ->sum('capacity'),
        ])->saveQuietly();
    }
}
