<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinanceEntityLoanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'principal' => $this->principal,
            'interest_amount' => $this->interest_amount,
            'total_amount' => $this->total_amount,
            'installment_count' => $this->installment_count,
            'started_at' => $this->started_at?->toDateString(),
            'status' => $this->status?->value,
            'installments' => FinanceEntityLoanInstallmentResource::collection($this->whenLoaded('installments')),
        ];
    }
}
