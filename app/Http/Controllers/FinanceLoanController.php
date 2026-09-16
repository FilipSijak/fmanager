<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\TakeOutCashLoanRequest;
use App\Http\Resources\FinanceEntityLoanResource;
use App\Services\FinanceService\FinanceService;
use App\Support\GameContext;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;

class FinanceLoanController extends Controller
{
    public function __construct(
        private readonly FinanceService $financeService,
        private readonly GameContext $gameContext,
    ) {}

    public function index(): JsonResponse
    {
        return ResponseHelper::success(
            FinanceEntityLoanResource::collection($this->financeService->getClubLoans())->resolve(request()),
        );
    }

    public function store(TakeOutCashLoanRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $loan = $this->financeService->takeOutCashLoan(
                (int) $data['amount'],
                (int) $data['length_months'],
                CarbonImmutable::parse($this->gameContext->instanceDate()),
            );

            return ResponseHelper::success(
                (new FinanceEntityLoanResource($loan))->toArray(request()),
                ResponseHelper::RESPONSE_SUCCESS_CODE,
            );
        } catch (DomainException $exception) {
            return ResponseHelper::error($exception->getMessage(), '', 422);
        }
    }
}
