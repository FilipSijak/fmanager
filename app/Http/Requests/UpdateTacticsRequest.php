<?php

namespace App\Http\Requests;

use App\Services\TacticsService\Mentality;
use App\Services\TacticsService\PassingStyle;
use App\Services\TacticsService\PressingIntensity;
use App\Services\TacticsService\TacklingStyle;
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
            'tackling' => ['required', Rule::enum(TacklingStyle::class)],
            'offside_trap' => ['required', 'boolean'],
            'counter_attack' => ['required', 'boolean'],
            'men_behind_ball' => ['required', 'boolean'],
            'free_kicks_left_player_id' => ['nullable', 'integer'],
            'free_kicks_right_player_id' => ['nullable', 'integer'],
            'corners_left_player_id' => ['nullable', 'integer'],
            'corners_right_player_id' => ['nullable', 'integer'],
            'playmaker_player_id' => ['nullable', 'integer'],
        ];
    }
}
