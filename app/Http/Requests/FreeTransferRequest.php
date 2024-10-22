<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FreeTransferRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'source_club_id' => 'required|integer|exists:clubs,id',
            'player_id' => 'required|integer|exists:players,id',
            'salary' => 'required|integer',
            'appearance' => 'integer',
            'assist' => 'integer',
            'goal' => 'integer',
            'league' => 'integer',
            'pc_promotion_salary_raise' => 'integer|between:0,100',
            'pc_demotion_salary_cut' => 'integer|between:0,100',
            'cup' => 'integer',
            'el' => 'integer',
        ];
    }
}
