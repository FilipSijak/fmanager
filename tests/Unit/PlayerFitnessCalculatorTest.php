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
    /** @return array<string, array{int, int, int, string, int, int, int}> */
    public static function matchLoads(): array
    {
        return [
            'full match' => [100, 90, 10, 'CM', 76, 10, 10],
            'substitute' => [100, 30, 10, 'ST', 92, 10, 10],
            'unused substitute' => [100, 0, 10, 'ST', 100, 10, 10],
            'goalkeeper' => [100, 90, 10, 'GK', 88, 10, 10],
            'high stamina' => [100, 90, 20, 'CM', 86, 10, 10],
            'high workrate' => [100, 90, 10, 'CM', 73, 20, 10],
            'high teamwork' => [100, 90, 10, 'CM', 73, 10, 20],
            'both high' => [100, 90, 10, 'CM', 70, 20, 20],
            'stamina offsets high effort' => [100, 90, 20, 'CM', 82, 20, 20],
            'low effort' => [100, 90, 10, 'CM', 81, 1, 1],
            'high effort goalkeeper' => [100, 90, 10, 'GK', 85, 20, 20],
            'high effort substitute' => [100, 30, 10, 'ST', 90, 20, 20],
            'attributes clamp to their valid range' => [100, 90, 99, 'CM', 82, 99, 99],
            'exhausted player' => [10, 90, 1, 'CM', 0, 10, 10],
        ];
    }

    #[Test]
    #[DataProvider('matchLoads')]
    public function condition_loss_reflects_minutes_stamina_position_workrate_and_teamwork(int $condition, int $minutes, int $stamina, string $position, int $expected, int $workrate, int $teamwork): void
    {
        $this->assertSame($expected, (new PlayerFitnessCalculator)->conditionAfterMatch($condition, $minutes, $stamina, $position, $workrate, $teamwork));
    }

    #[Test]
    public function fatigued_players_perform_worse_and_serialized_matches_resume_without_compounding_the_loss(): void
    {
        $player = new MatchPlayer(1, 'CM', 10, 10, 10, 10, 10, 10, 10, 10, 10, workrate: 20, teamwork: 20);
        $halfTime = MatchPlayer::fromArray($player->fatigued(45)->toArray());
        $fullTime = $halfTime->fatigued(90);

        $this->assertEqualsWithDelta(9.25, $halfTime->passing, 0.00001);
        $this->assertEqualsWithDelta(8.5, $fullTime->passing, 0.00001);
        $this->assertEqualsWithDelta($player->fatigued(90)->passing, $fullTime->passing, 0.00001);
        $this->assertSame(90, $fullTime->playedMinutes);
        $this->assertSame(20, $fullTime->workrate);
        $this->assertSame(20, $fullTime->teamwork);
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
