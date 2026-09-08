<?php

namespace Tests\Unit\Domain\PlayerDevelopment;

use App\Domain\PlayerDevelopment\PlayerDevelopmentCategory;
use App\Domain\PlayerDevelopment\TrainingCategory;
use Tests\TestCase;

class PlayerDevelopmentCategoryTest extends TestCase
{
    public function test_training_categories_map_to_development_categories(): void
    {
        $this->assertSame(
            PlayerDevelopmentCategory::Physical,
            PlayerDevelopmentCategory::fromTrainingCategoryId(TrainingCategory::Physical->value)
        );
        $this->assertSame(
            PlayerDevelopmentCategory::Mental,
            PlayerDevelopmentCategory::fromTrainingCategoryId(TrainingCategory::Tactical->value)
        );
        $this->assertSame(
            PlayerDevelopmentCategory::Technical,
            PlayerDevelopmentCategory::fromTrainingCategoryId(TrainingCategory::Technical->value)
        );
        $this->assertNull(
            PlayerDevelopmentCategory::fromTrainingCategoryId(TrainingCategory::Goalkeeping->value)
        );
    }

    public function test_development_categories_expose_their_training_category_ids(): void
    {
        $this->assertSame(TrainingCategory::Physical->value, PlayerDevelopmentCategory::Physical->trainingCategoryId());
        $this->assertSame(TrainingCategory::Tactical->value, PlayerDevelopmentCategory::Mental->trainingCategoryId());
        $this->assertSame(TrainingCategory::Technical->value, PlayerDevelopmentCategory::Technical->trainingCategoryId());
    }
}
