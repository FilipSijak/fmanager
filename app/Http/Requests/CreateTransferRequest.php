<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class CreateTransferRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'source_club_id' => 'required|integer',
            'target_club_id'=> 'required|integer',
            'player_id' => 'required|integer',
            'transfer_type' => 'required|integer',
            'loan_start' => 'string|nullable',
            'loan_end' => 'string|nullable',
            'amount' => 'integer|required',
            'installments' => 'integer|required',
        ];
    }

    public function validationData(): array
    {
        $validationData = parent::validationData();

        $validationData['id'] = $this->route('id');

        return $validationData;
    }

    public function response(array $errors)
    {
        throw new ValidationException($errors);
    }
}
