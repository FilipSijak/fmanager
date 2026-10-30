<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlayerOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'player_id' => ['required', 'integer'],
            'transfer_type' => ['required', 'integer', Rule::in([1, 3])],
            'amount' => ['required_if:transfer_type,1', 'integer', 'min:0', 'max:2000000000'],
            'installments' => ['sometimes', 'integer', 'min:0', 'max:60'],
            'salary' => ['required_if:transfer_type,3', 'integer', 'min:1', 'max:100000000'],
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
