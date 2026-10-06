<?php

namespace App\Http\Controllers;

use App\ConstructionPaymentMethod;
use App\Helpers\ResponseHelper;
use App\Http\Requests\BuildStadiumRequest;
use App\Http\Requests\StartStadiumStandConstructionRequest;
use App\Http\Resources\StadiumCommercialCategoryResource;
use App\Http\Resources\StadiumCommercialVenueResource;
use App\Http\Resources\StadiumResource;
use App\Http\Resources\StadiumStandConstructionResource;
use App\Models\BaseData\BaseCommercialCategory;
use App\Models\Stadium;
use App\Models\StadiumStandConstruction;
use App\Repositories\StadiumRepository;
use App\Services\CommercialService\CommercialVenueSize;
use App\Services\StadiumService\StadiumService;
use App\StadiumConstructionType;
use App\Support\GameContext;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;

class StadiumController extends Controller
{
    public function __construct(
        private readonly GameContext $gameContext,
        private readonly StadiumRepository $stadiumRepository,
        private readonly StadiumService $stadiumService,
    ) {}

    public function show(): JsonResponse
    {
        return ResponseHelper::success(
            (new StadiumResource($this->managedStadium()))->toArray(request()),
            ResponseHelper::RESPONSE_SUCCESS_CODE,
        );
    }

    public function buildableCommercialCategories(): JsonResponse
    {
        $stadium = $this->managedStadium();

        return ResponseHelper::success(
            $this->stadiumService->buildableCommercialCategoriesForStadium($stadium)
                ->map(fn (BaseCommercialCategory $category): array => [
                    ...(new StadiumCommercialCategoryResource($category))->resolve(request()),
                    'costs' => $this->venueCostsBySize($stadium, $category),
                ])
                ->all(),
        );
    }

    public function standExpansionCost(int $standId, StartStadiumStandConstructionRequest $request): JsonResponse
    {
        $stadium = $this->managedStadium();
        $stand = $this->stadiumRepository->standForStadium($stadium, $standId);
        $targetCapacity = (int) $request->validated('target_capacity');
        $capacityIncrease = $targetCapacity - (int) $stand->capacity;

        try {
            return ResponseHelper::success([
                'capacity_increase' => $capacityIncrease,
                'maximum_capacity' => $this->stadiumService->maximumCapacityForStand($stadium, $stand->position),
                'cost' => $capacityIncrease > 0 ? $this->stadiumService->stadiumExpansionCost($stadium, $capacityIncrease) : 0,
                'duration_weeks' => $capacityIncrease > 0 && $capacityIncrease % 1000 === 0
                    ? $this->stadiumService->durationInWeeks($capacityIncrease)
                    : 0,
            ]);
        } catch (DomainException $exception) {
            return $this->domainError($exception);
        }
    }

    /** @return array<string, int> */
    private function venueCostsBySize(Stadium $stadium, BaseCommercialCategory $category): array
    {
        $costs = [];

        foreach (CommercialVenueSize::cases() as $size) {
            try {
                $costs[strtolower($size->name)] = $this->stadiumService->venueConstructionCost($stadium, $category, $size);
            } catch (DomainException) {
                // No cost configured for this size at this category; omit it.
            }
        }

        return $costs;
    }

    public function build(BuildStadiumRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $construction = $this->stadiumService->buildConstruction(
                $this->managedStadium(),
                StadiumConstructionType::from($data['building_type']),
                isset($data['stand_id']) ? (int) $data['stand_id'] : null,
                isset($data['target_capacity']) ? (int) $data['target_capacity'] : null,
                isset($data['category_id']) ? (int) $data['category_id'] : null,
                isset($data['size']) ? CommercialVenueSize::from((int) $data['size']) : null,
                ConstructionPaymentMethod::from($data['payment_method']),
                (int) $data['length_years'],
                CarbonImmutable::parse($this->gameContext->instanceDate()),
            );

            $resource = $construction instanceof StadiumStandConstruction
                ? new StadiumStandConstructionResource($construction)
                : new StadiumCommercialVenueResource($construction->load('category'));

            return ResponseHelper::success(
                $resource->toArray(request()),
                ResponseHelper::RESPONSE_SUCCESS_CODE,
            );
        } catch (DomainException $exception) {
            return $this->domainError($exception);
        }
    }

    public function demolishCommercialVenue(int $venueId): JsonResponse
    {
        try {
            $demolitionCost = $this->stadiumService->demolishCommercialVenue(
                $this->managedStadium(),
                $venueId,
            );

            return ResponseHelper::success([
                'demolition_cost' => $demolitionCost,
            ]);
        } catch (DomainException $exception) {
            return $this->domainError($exception);
        }
    }

    private function managedStadium(): Stadium
    {
        return $this->stadiumRepository->managedStadiumForInstance($this->gameContext->instanceId());
    }

    private function domainError(DomainException $exception): JsonResponse
    {
        return ResponseHelper::error($exception->getMessage(), '', 422);
    }
}
