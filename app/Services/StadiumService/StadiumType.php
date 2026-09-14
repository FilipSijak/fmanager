<?php

namespace App\Services\StadiumService;

enum StadiumType: string
{
    case VILLAGE = 'village';
    case LOCAL = 'local';
    case REGIONAL = 'regional';
    case GLOBAL = 'global';

    public static function fromCapacity(int $capacity): self
    {
        return match (true) {
            $capacity <= 1000 => self::VILLAGE,
            $capacity <= 20000 => self::LOCAL,
            $capacity <= 60000 => self::REGIONAL,
            default => self::GLOBAL,
        };
    }

    public static function fromClubRank(int $rank): self
    {
        return match (true) {
            $rank <= 3 => self::VILLAGE,
            $rank <= 11 => self::LOCAL,
            $rank <= 16 => self::REGIONAL,
            default => self::GLOBAL,
        };
    }

    public function allowsCornerStands(): bool
    {
        return match ($this) {
            self::VILLAGE, self::LOCAL => false,
            self::REGIONAL, self::GLOBAL => true,
        };
    }

    public function maximumCapacity(): int
    {
        return match ($this) {
            self::VILLAGE => 1000,
            self::LOCAL => 20000,
            self::REGIONAL => 60000,
            self::GLOBAL => 100000,
        };
    }

    public function commercialLimit(): int
    {
        return StadiumConfig::COMMERCIAL_LIMITS[$this->value];
    }
}
