<?php

namespace App\Http\Requests;

use App\ConstructionPaymentMethod;
use App\Services\CommercialService\CommercialVenueSize;
use App\StadiumConstructionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BuildStadiumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'building_type' => ['required', Rule::enum(StadiumConstructionType::class)],
            'stand_id' => ['nullable', 'integer', 'required_if:building_type,stand'],
            'target_capacity' => ['nullable', 'integer', 'required_if:building_type,stand'],
            'category_id' => ['nullable', 'integer', 'required_if:building_type,commercial_venue'],
            'size' => ['nullable', 'integer', Rule::enum(CommercialVenueSize::class), 'required_if:building_type,commercial_venue'],
            'payment_method' => ['required', Rule::enum(ConstructionPaymentMethod::class)],
            'length_years' => [
                'required',
                'integer',
                'min:0',
                'max:12',
                'required_if:payment_method,mortgage',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->sometimes('length_years', ['in:0'], fn (): bool => $this->input('payment_method') === 'cash');
        $validator->sometimes('length_years', ['min:2'], fn (): bool => $this->input('payment_method') === 'mortgage');
    }
}
