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
            $capacity <= 5000 => self::LOCAL,
            $capacity <= 30000 => self::REGIONAL,
            default => self::GLOBAL,
        };
    }

    public function maximumCapacity(): int
    {
        return match ($this) {
            self::VILLAGE => 1000,
            self::LOCAL => 5000,
            self::REGIONAL => 30000,
            self::GLOBAL => 100000,
        };
    }

    public function commercialLimit(): int
    {
        return match ($this) {
            self::VILLAGE => 1,
            self::LOCAL => 3,
            self::REGIONAL => 6,
            self::GLOBAL => 10,
        };
    }
}
