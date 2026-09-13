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
            $capacity <= 20000 => self::VILLAGE,
            $capacity <= 40000 => self::LOCAL,
            $capacity <= 70000 => self::REGIONAL,
            default => self::GLOBAL,
        };
    }

    public function maximumCapacity(): int
    {
        return match ($this) {
            self::VILLAGE => 20000,
            self::LOCAL => 40000,
            self::REGIONAL => 70000,
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
