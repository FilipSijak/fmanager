<?php

namespace App\Http\Controllers;

use App\ConstructionPaymentMethod;
use App\Helpers\ResponseHelper;
use App\Http\Requests\BuildStadiumCommercialVenueRequest;
use App\Http\Requests\BuildStadiumRequest;
use App\Http\Requests\StartStadiumStandConstructionRequest;
use App\Http\Resources\StadiumCommercialCategoryResource;
use App\Http\Resources\StadiumCommercialVenueResource;
use App\Http\Resources\StadiumResource;
use App\Http\Resources\StadiumStandConstructionResource;
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
        return ResponseHelper::success(
            StadiumCommercialCategoryResource::collection(
                $this->stadiumService->buildableCommercialCategoriesForStadium($this->managedStadium()),
            )->resolve(request()),
        );
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

    public function buildCommercialVenue(BuildStadiumCommercialVenueRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $venue = $this->stadiumService->buildCommercialVenue(
                $this->managedStadium(),
                (int) $data['category_id'],
                CommercialVenueSize::from((int) $data['size']),
            );

            return ResponseHelper::success(
                (new StadiumCommercialVenueResource($venue->load('category')))->toArray(request()),
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

    public function startStandConstruction(
        StartStadiumStandConstructionRequest $request,
        int $standId,
    ): JsonResponse {
        $data = $request->validated();

        try {
            $stand = $this->stadiumRepository->standForStadium(
                $this->managedStadium(),
                $standId,
            );
            $construction = $this->stadiumService->startStandConstruction(
                $stand,
                (int) $data['target_capacity'],
                CarbonImmutable::parse($this->gameContext->instanceDate()),
            );

            return ResponseHelper::success(
                (new StadiumStandConstructionResource($construction))->toArray(request()),
                ResponseHelper::RESPONSE_SUCCESS_CODE,
            );
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
