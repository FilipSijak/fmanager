<?php

namespace App\Domain\PlayerDevelopment;

use App\Services\PersonService\PersonConfig\Player\PlayerFields;

enum PlayerDevelopmentCategory: string
{
    case Technical = 'technical';
    case Mental = 'mental';
    case Physical = 'physical';

    public static function fromTrainingCategoryId(int $trainingCategoryId): ?self
    {
        return match ($trainingCategoryId) {
            1 => self::Physical,
            2 => self::Mental,
            3 => self::Technical,
            default => null,
        };
    }

    public function currentPotentialProperty(): string
    {
        return match ($this) {
            self::Technical => 'currentTechnical',
            self::Mental => 'currentMental',
            self::Physical => 'currentPhysical',
        };
    }

    public function trainingCategoryId(): int
    {
        return match ($this) {
            self::Physical => 1,
            self::Mental => 2,
            self::Technical => 3,
        };
    }

    public function fields(): array
    {
        return match ($this) {
            self::Technical => PlayerFields::TECHNICAL_FIELDS,
            self::Mental => PlayerFields::MENTAL_FIELDS,
            self::Physical => PlayerFields::PHYSICAL_FIELDS,
        };
    }
}
