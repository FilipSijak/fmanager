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
        return match (TrainingCategory::tryFrom($trainingCategoryId)) {
            TrainingCategory::Physical => self::Physical,
            TrainingCategory::Tactical => self::Mental,
            TrainingCategory::Technical => self::Technical,
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
            self::Physical => TrainingCategory::Physical->value,
            self::Mental => TrainingCategory::Tactical->value,
            self::Technical => TrainingCategory::Technical->value,
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
