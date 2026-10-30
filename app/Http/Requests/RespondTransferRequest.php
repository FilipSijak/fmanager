<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RespondTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['accept', 'reject', 'counter', 'contract', 'withdraw'])],
            'amount' => ['required_if:action,counter', 'integer', 'min:0', 'max:2000000000'],
            'salary' => ['required_if:action,contract', 'integer', 'min:1', 'max:100000000'],
            'transfer_fee' => ['sometimes', 'integer', 'min:0', 'max:2000000000'],
            'appearance' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
            'goal' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
            'assist' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
            'clean_sheet' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
            'league' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
            'promotion' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
            'cup' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
            'el' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
            'cl' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
        ];
    }
}
