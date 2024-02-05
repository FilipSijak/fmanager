<?php

namespace App\Services\TransferService;

use App\Services\TransferService\TransferRequest\TransferRequestValidator;

class TransferService
{
    private TransferRequestValidator $transferRequestValidator;

    public function __construct(TransferRequestValidator $transferRequestValidator)
    {
        $this->transferRequestValidator = $transferRequestValidator;
    }

    public function processTransferBids()
    {

    }

    public function makeTransferRequest(array $requestParams)
    {
        switch ($requestParams) {
            case TransferTypes::FREE_TRANSFER:
                $validationErrors = $this->transferRequestValidator->validateFreeTransferRequest($requestParams);

                if (!empty($validationErrors)) {
                    return false;
                }

                break;
            case TransferTypes::LOAN_TRANSFER:
                $validationErrors = $this->transferRequestValidator->validateLoanTransferRequest($requestParams);

                if (!empty($validationErrors)) {
                    return false;
                }

                break;
            case TransferTypes::PERMANENT_TRANSFER:
                $validationErrors = $this->transferRequestValidator->validatePermanentTransferRequest($requestParams);

                if (!empty($validationErrors)) {
                    return false;
                }

                break;
        }
    }

    public function transferRequestDecision()
    {

    }
}
