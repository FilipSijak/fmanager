<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveSquadLineupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assignments' => ['present', 'array', 'max:18'],
            'assignments.*.slot' => ['required', 'string', 'max:10', 'distinct'],
            'assignments.*.player_id' => ['required', 'integer', 'distinct'],
            'assignments.*.position' => ['required', 'string', 'max:20'],
        ];
    }
}
