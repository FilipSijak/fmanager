<?php

namespace App\Http\Requests;

use App\Services\TacticsService\Mentality;
use App\Services\TacticsService\PassingStyle;
use App\Services\TacticsService\PressingIntensity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTacticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'formation_id' => ['required', 'integer', Rule::exists('base_formations', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'mentality' => ['required', Rule::enum(Mentality::class)],
            'pressing' => ['required', Rule::enum(PressingIntensity::class)],
            'passing' => ['required', Rule::enum(PassingStyle::class)],
        ];
    }
}
