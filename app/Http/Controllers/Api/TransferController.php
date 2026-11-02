<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateTransferRequest;
use App\Http\Requests\FreeTransferRequest;
use App\Services\TransferService\TransferService;

class TransferController extends Controller
{
    public function __construct(TransferService $transferService)
    {
        $this->transferService = $transferService;
    }

    public function makeTransferRequest(CreateTransferRequest $request)
    {
        $this->transferService->startTransferNegotiations($request);
    }

    public function makeFreeTransferRequest(FreeTransferRequest $request)
    {
        $this->transferService->freeTransferRequest($request);
    }
}
