<?php

namespace App\Http\Requests;

use App\Services\ContractService\Domain\PlayerContractRenewal;
use Illuminate\Foundation\Http\FormRequest;

class RenewPlayerContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'months' => ['required', 'integer', 'min:'.PlayerContractRenewal::MIN_MONTHS, 'max:'.PlayerContractRenewal::MAX_MONTHS],
            'expected_contract_end' => ['required', 'date_format:Y-m-d'],
            'salary' => ['required', 'integer', 'min:1', 'max:100000000'],
            'transfer_fee' => ['sometimes', 'integer', 'min:0', 'max:2000000000'],
        ];
        foreach (PlayerContractRenewal::MONEY_FIELDS as $field) {
            if ($field !== 'salary') {
                $rules[$field] = ['sometimes', 'integer', 'min:0', 'max:100000000'];
            }
        }

        return $rules;
    }
}
