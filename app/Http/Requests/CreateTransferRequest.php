<?php

namespace App\Http\Requests;

use App\Models\Player;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class CreateTransferRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'source_club_id' => 'required|integer',
            'target_club_id'=> 'required|integer|different:source_club_id',
            'player_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) {
                    $sourceClubId = $this->input('source_club_id');

                    if (! $sourceClubId) {
                        return;
                    }

                    $alreadyAtSourceClub = Player::query()
                        ->whereKey($value)
                        ->where('club_id', $sourceClubId)
                        ->exists();

                    if ($alreadyAtSourceClub) {
                        $fail('The selected player already belongs to the source club.');
                    }
                },
            ],
            'transfer_type' => 'required|integer',
            'loan_start' => 'string|nullable',
            'loan_end' => 'string|nullable',
            'amount' => 'integer|required',
            'installments' => 'integer',
        ];
    }

    public function validationData(): array
    {
        $validationData = parent::validationData();

        $validationData['id'] = $this->route('id');

        return $validationData;
    }

    public function response(array $errors)
    {
        throw new ValidationException($errors);
    }
}
