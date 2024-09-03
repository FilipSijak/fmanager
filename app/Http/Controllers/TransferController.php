<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateTransferRequest;
use App\Services\TransferService\TransferService;

class TransferController extends Controller
{
    public function __construct(TransferService $transferService)
    {
        $this->transferService = $transferService;
    }

    public function makeTransfer(CreateTransferRequest $request)
    {
        $this->transferService->startTransferNegotiations($request);
    }
}
