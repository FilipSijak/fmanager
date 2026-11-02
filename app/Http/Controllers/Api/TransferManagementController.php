<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\RespondTransferRequest;
use App\Http\Requests\StorePlayerOfferRequest;
use App\Services\TransferService\TransferManagementService;
use Illuminate\Http\JsonResponse;

class TransferManagementController extends Controller
{
    public function __construct(private readonly TransferManagementService $transfers) {}

    public function store(StorePlayerOfferRequest $request): JsonResponse
    {
        $transfer = $this->transfers->offer($request->validated());

        return ResponseHelper::success(['id' => $transfer->id], 201);
    }

    public function respond(RespondTransferRequest $request, int $transferId): JsonResponse
    {
        $transfer = $this->transfers->respond($transferId, $request->validated());

        return ResponseHelper::success(['id' => $transfer->id]);
    }
}
