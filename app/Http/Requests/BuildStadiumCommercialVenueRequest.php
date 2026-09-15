<?php

namespace App\Http\Requests;

use App\Services\CommercialService\CommercialVenueSize;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BuildStadiumCommercialVenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer'],
            'size' => ['required', 'integer', Rule::enum(CommercialVenueSize::class)],
        ];
    }
}
