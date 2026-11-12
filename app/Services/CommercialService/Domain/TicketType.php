<?php

namespace App\Services\CommercialService\Domain;

enum TicketType: string
{
    case LEAGUE = 'league';
    case CUP = 'cup';
    case CONTINENTAL = 'continental';

    public static function forCompetition(string $type, string $scope): self
    {
        if ($scope === 'continental') {
            return self::CONTINENTAL;
        }

        return $type === 'league' ? self::LEAGUE : self::CUP;
    }

    public function multiplier(): float
    {
        return match ($this) {
            self::LEAGUE => 1.0,
            self::CUP => 0.8,
            self::CONTINENTAL => 1.2,
        };
    }
}
