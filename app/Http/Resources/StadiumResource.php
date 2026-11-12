<?php

namespace App\Http\Resources;

use App\Services\CommercialService\Domain\TicketPriceCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StadiumResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type?->value,
            'commercial_limit' => $this->commercial_limit,
            'capacity' => $this->capacity,
            'ticket_price' => $this->ticket_price,
            'ticket_prices' => $this->ticket_price === null ? null : (new TicketPriceCalculator)->prices((int) $this->ticket_price),
            'active_capacity' => $this->active_capacity,
            'stands' => StadiumStandResource::collection($this->whenLoaded('stands')),
            'commercial_venues' => StadiumCommercialVenueResource::collection($this->whenLoaded('commercialVenues')),
        ];
    }
}
