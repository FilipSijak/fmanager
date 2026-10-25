<?php

namespace App\Http\Requests;

use App\Services\TacticsService\Mentality;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeLiveMatchTacticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'formation_id' => ['required', 'integer'],
            'mentality' => ['required', Rule::enum(Mentality::class)],
            'assignments' => ['present', 'array', 'max:11'],
            'assignments.*.slot' => ['required', 'string', 'max:10', 'distinct'],
            'assignments.*.player_id' => ['present', 'nullable', 'integer', 'distinct'],
        ];
    }

    /** @return array<string, ?int> Player id for each formation slot id. */
    public function assignments(): array
    {
        return collect($this->validated('assignments'))
            ->mapWithKeys(fn (array $assignment): array => [$assignment['slot'] => $assignment['player_id']])
            ->all();
    }
}
