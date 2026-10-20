<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BrowseSeasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'season_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
