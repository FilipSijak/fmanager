<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\BuildStadiumCommercialVenueRequest;
use App\Http\Requests\StartStadiumStandConstructionRequest;
use App\Http\Resources\StadiumCommercialCategoryResource;
use App\Http\Resources\StadiumCommercialVenueResource;
use App\Http\Resources\StadiumResource;
use App\Http\Resources\StadiumStandConstructionResource;
use App\Models\Stadium;
use App\Repositories\StadiumRepository;
use App\Services\CommercialService\CommercialVenueSize;
use App\Services\StadiumService\StadiumService;
use App\Services\StadiumService\StadiumStandConstructionService;
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
        private readonly StadiumStandConstructionService $constructionService,
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
            $construction = $this->constructionService->startConstruction(
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
