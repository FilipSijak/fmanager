<?php

namespace App\Services\SeasonTicketService\Domain;

class SeasonTicketCalculator
{
    /** @return array{capacity: int, quantity: int, home_games: int, league_ticket_price: int, ticket_price: int, amount: int}|null */
    public function offer(int $capacity, int $leaguePrice, int $homeGames, float $demand = 1.0): ?array
    {
        if ($homeGames <= 0) {
            return null;
        }
        $capacity = max(0, $capacity);
        $allocation = intdiv($capacity * 40, 100);
        $quantity = (int) floor($allocation * max(0.0, min(1.0, $demand)));
        $leaguePrice = max(0, $leaguePrice);
        $ticketPrice = $leaguePrice * $homeGames;

        return [
            'capacity' => $capacity,
            'quantity' => $quantity,
            'home_games' => $homeGames,
            'league_ticket_price' => $leaguePrice,
            'ticket_price' => $ticketPrice,
            'amount' => $quantity * $ticketPrice,
        ];
    }
}
