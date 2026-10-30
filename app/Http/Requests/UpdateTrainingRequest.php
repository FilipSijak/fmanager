<?php

namespace App\Http\Requests;

use App\Domain\PlayerDevelopment\TrainingCategory;
use App\Services\TrainingService\TrainingIntensity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTrainingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'player_ids' => ['required', 'array', 'min:1', 'max:250'],
            'player_ids.*' => ['required', 'integer', 'distinct'],
            'schedule' => ['required', 'array:physical,tactical,technical,goalkeeping'],
        ];

        foreach (TrainingCategory::cases() as $category) {
            $rules['schedule.'.strtolower($category->name)] = ['required', 'integer', Rule::enum(TrainingIntensity::class)];
        }

        return $rules;
    }
}
