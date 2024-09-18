<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FreeTransferRequest extends FormRequest
{

    public function authorize()
    {
        return false;
    }

    public function rules()
    {
        return [
            'source_club_id' => 'required|integer|exists:clubs,id',
            'player_id' => 'required|integer|exists:players,id',
            'salary' => 'required|integer',
            'appearance' => 'required|integer',
            'assist' => 'required|integer',
            'goal' => 'required|integer',
            'league' => 'required|integer',
            'pc_promotion_salary_raise' => 'required|integer|between:0,100',
            'pc_demotion_salary_cut' => 'required|integer|between:0,100',
            'cup' => 'required|integer',
            'el' => 'required|integer',
            'agent_fee' => 'required|integer',
        ];
    }
}
