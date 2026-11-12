<?php

namespace App\Services\CommercialService\Domain;

class StadiumCommercialDefaults
{
    public function __construct(private readonly TicketPriceCalculator $ticketPriceCalculator) {}

    /** @param array{club_rank: int, competition_rank: int, country_rank: int} $inputs */
    public function ticketPrice(array $inputs): int
    {
        return $this->ticketPriceCalculator->calculate($inputs['club_rank'], $inputs['competition_rank'], $inputs['country_rank']);
    }

    /**
     * @param  list<array{id: int, base_price: int}>  $products
     * @return list<array{instance_id: int, stadium_id: int, base_product_id: int, base_price: int, price_change_coef: float, is_available: bool}>
     */
    public function productRows(int $instanceId, int $stadiumId, array $products): array
    {
        return array_map(fn (array $product): array => [
            'instance_id' => $instanceId,
            'stadium_id' => $stadiumId,
            'base_product_id' => $product['id'],
            'base_price' => $product['base_price'],
            'price_change_coef' => 1.0,
            'is_available' => true,
        ], $products);
    }
}
