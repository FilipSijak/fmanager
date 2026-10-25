<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubstituteLiveMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'player_off_id' => ['required', 'integer'],
            'player_on_id' => ['required', 'integer', 'different:player_off_id'],
        ];
    }
}
