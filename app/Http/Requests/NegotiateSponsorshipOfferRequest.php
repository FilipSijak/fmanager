<?php

namespace App\Http\Requests;

use App\Services\FinanceService\Domain\SponsorshipCalculator;
use Illuminate\Foundation\Http\FormRequest;

class NegotiateSponsorshipOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['annual_amount' => ['required', 'integer', 'min:1', 'max:'.SponsorshipCalculator::MAX_ANNUAL_AMOUNT]];
    }
}
