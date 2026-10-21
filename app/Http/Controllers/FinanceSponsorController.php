<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\SponsorshipContractResource;
use App\Services\FinanceService\SponsorshipService;
use Illuminate\Http\JsonResponse;

class FinanceSponsorController extends Controller
{
    public function index(SponsorshipService $sponsorships): JsonResponse
    {
        return ResponseHelper::success(
            SponsorshipContractResource::collection($sponsorships->getClubContracts())->resolve(request()),
        );
    }
}
