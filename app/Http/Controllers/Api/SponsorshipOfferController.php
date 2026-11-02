<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\NegotiateSponsorshipOfferRequest;
use App\Http\Resources\SponsorshipOfferResource;
use App\Services\FinanceService\SponsorshipOfferService;
use DomainException;
use Illuminate\Http\JsonResponse;

class SponsorshipOfferController extends Controller
{
    public function __construct(private readonly SponsorshipOfferService $offers) {}

    public function index(): JsonResponse
    {
        return ResponseHelper::success(SponsorshipOfferResource::collection($this->offers->pendingForManagedClub())->resolve(request()));
    }

    public function accept(int $offerId): JsonResponse
    {
        return $this->respond($offerId, 'accept');
    }

    public function history(): JsonResponse
    {
        return ResponseHelper::success(SponsorshipOfferResource::collection($this->offers->historyForManagedClub())->resolve(request()));
    }

    public function reject(int $offerId): JsonResponse
    {
        return $this->respond($offerId, 'reject');
    }

    public function negotiate(NegotiateSponsorshipOfferRequest $request, int $offerId): JsonResponse
    {
        return $this->respond($offerId, 'negotiate', $request->integer('annual_amount'));
    }

    private function respond(int $offerId, string $action, ?int $annualAmount = null): JsonResponse
    {
        try {
            return ResponseHelper::success((new SponsorshipOfferResource($this->offers->respond($offerId, $action, $annualAmount)))->resolve(request()));
        } catch (DomainException $exception) {
            return ResponseHelper::error($exception->getMessage(), '', 422);
        }
    }
}
