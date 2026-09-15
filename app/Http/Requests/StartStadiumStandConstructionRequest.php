<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartStadiumStandConstructionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_capacity' => ['required', 'integer', 'min:0'],
        ];
    }
}
