<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TakeOutBankLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
            'length_years' => ['required', 'integer', 'min:1', 'max:30'],
        ];
    }
}
