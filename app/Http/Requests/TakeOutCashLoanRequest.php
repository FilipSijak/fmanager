<?php

namespace App\Http\Requests;

use App\GameEntityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TakeOutCashLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
            'length_months' => ['required', 'integer', 'min:1', 'max:36'],
            'lender' => ['sometimes', 'string', Rule::in([GameEntityType::BANK->value, GameEntityType::LOAN_SHARKS->value])],
        ];
    }
}
