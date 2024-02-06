<?php

namespace App\Services\TransferService\TransferRequest;

class TransferRequestValidator
{
    const LOAN_TRANSFER_FIELDS      = [
        'source_club_id',
        'target_club_id',
        'player_id',
        'season_id',
        'offer_date',
        'amount',
        'loan_start',
        'loan_end'
    ];
    const PERMANENT_TRANSFER_FIELDS = [
        'source_club_id',
        'target_club_id',
        'player_id',
        'season_id',
        'offer_date',
        'amount',
    ];
    const FREE_TRANSFER_FIELDS      = [
        'source_club_id',
        'player_id',
        'season_id',
        'offer_date',
    ];

    public function validatePermanentTransferRequest(array $requestParams): array
    {
        $missingFields = [];

        foreach (self::PERMANENT_TRANSFER_FIELDS as $field) {
            if (!isset($requestParams[$field])) {
                $missingFields[] = $field;
            }
        }

        return $missingFields;
    }

    public function validateLoanTransferRequest(array $requestParams): array
    {
        $missingFields = [];

        foreach (self::LOAN_TRANSFER_FIELDS as $field) {
            if (!isset($requestParams[$field])) {
                $missingFields[] = $field;
            }
        }

        return $missingFields;
    }

    public function validateFreeTransferRequest(array $requestParams): array
    {
        $missingFields = [];

        foreach (self::FREE_TRANSFER_FIELDS as $field) {
            if (!isset($requestParams[$field])) {
                $missingFields[] = $field;
            }
        }

        return $missingFields;
    }
}
