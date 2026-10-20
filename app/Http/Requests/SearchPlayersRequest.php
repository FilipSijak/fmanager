<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SearchPlayersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:'.intdiv(config('elasticsearch.max_results'), max(1, $this->integer('limit', 20)))],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['page.max' => 'Refine your search to view more matches.'];
    }
}
