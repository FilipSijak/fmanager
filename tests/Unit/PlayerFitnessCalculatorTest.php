<?php

namespace Tests\Unit;

use App\Domain\PlayerDevelopment\TrainingCategory;
use App\Services\MatchEngineService\Data\MatchPlayer;
use App\Services\TrainingService\Data\TrainingScheduleData;
use App\Services\TrainingService\PlayerFitnessCalculator;
use App\Services\TrainingService\TrainingIntensity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PlayerFitnessCalculatorTest extends TestCase
{
    /** @return array<string, array{int, int, int, string, int}> */
    public static function matchLoads(): array
    {
        return [
            'full match' => [100, 90, 10, 'CM', 76],
            'substitute' => [100, 30, 10, 'ST', 92],
            'unused substitute' => [100, 0, 10, 'ST', 100],
            'goalkeeper' => [100, 90, 10, 'GK', 88],
            'high stamina' => [100, 90, 20, 'CM', 86],
            'exhausted player' => [10, 90, 1, 'CM', 0],
        ];
    }

    #[Test]
    #[DataProvider('matchLoads')]
    public function condition_loss_reflects_minutes_stamina_and_position(int $condition, int $minutes, int $stamina, string $position, int $expected): void
    {
        $this->assertSame($expected, (new PlayerFitnessCalculator)->conditionAfterMatch($condition, $minutes, $stamina, $position));
    }

    #[Test]
    public function fatigued_players_perform_worse_and_serialized_matches_resume_without_compounding_the_loss(): void
    {
        $player = new MatchPlayer(1, 'CM', 10, 10, 10, 10, 10, 10, 10, 10, 10);
        $halfTime = MatchPlayer::fromArray($player->fatigued(45)->toArray());
        $fullTime = $halfTime->fatigued(90);

        $this->assertEqualsWithDelta(9.4, $halfTime->passing, 0.00001);
        $this->assertEqualsWithDelta(8.8, $fullTime->passing, 0.00001);
        $this->assertEqualsWithDelta($player->fatigued(90)->passing, $fullTime->passing, 0.00001);
        $this->assertSame(90, $fullTime->playedMinutes);
    }

    #[Test]
    public function poor_condition_increases_injury_risk_and_rest_has_no_training_risk(): void
    {
        $calculator = new PlayerFitnessCalculator;
        $hard = [new TrainingScheduleData(TrainingCategory::Physical, TrainingIntensity::Hard)];
        $light = [new TrainingScheduleData(TrainingCategory::Physical, TrainingIntensity::Light)];
        $rest = [new TrainingScheduleData(TrainingCategory::Physical, TrainingIntensity::None)];

        $this->assertGreaterThan($calculator->matchInjuryProbability(100, 90), $calculator->matchInjuryProbability(60, 90));
        $this->assertGreaterThan($calculator->trainingInjuryProbability(100, 'CM', $light), $calculator->trainingInjuryProbability(100, 'CM', $hard));
        $this->assertGreaterThan($calculator->trainingInjuryProbability(100, 'CM', $hard), $calculator->trainingInjuryProbability(70, 'CM', $hard));
        $this->assertSame(0.0, $calculator->trainingInjuryProbability(70, 'CM', $rest));
        $this->assertSame(0.0, $calculator->matchInjuryProbability(50, 0));
        $this->assertSame(0.0, $calculator->trainingInjuryProbability(100, 'CM', [new TrainingScheduleData(TrainingCategory::Goalkeeping, TrainingIntensity::Hard)]));
    }
}
