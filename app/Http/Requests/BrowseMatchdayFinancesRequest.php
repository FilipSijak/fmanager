<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BrowseMatchdayFinancesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['matchday_page' => ['sometimes', 'integer', 'min:1', 'max:100000']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'matchday_page.integer' => 'Choose a valid matchday page.',
            'matchday_page.min' => 'Choose a valid matchday page.',
            'matchday_page.max' => 'Choose a valid matchday page.',
        ];
    }
}
