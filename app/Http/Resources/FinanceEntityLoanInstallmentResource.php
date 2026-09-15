<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinanceEntityLoanInstallmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'installment_number' => $this->installment_number,
            'amount' => $this->amount,
            'due_date' => $this->due_date?->toDateString(),
            'paid_at' => $this->paid_at?->toISOString(),
        ];
    }
}
