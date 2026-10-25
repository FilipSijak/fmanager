<?php

namespace Tests\Unit\Services\MatchEngineService;

use App\Services\MatchEngineService\Data\MatchTeam;
use App\Services\MatchEngineService\MatchSimulationEngine;
use App\Services\TacticsService\FormationTendency;
use App\Services\TacticsService\Mentality;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

class MatchSimulationEngineTest extends TestCase
{
    /** @return array<string, array{FormationTendency, Mentality, float, float}> */
    public static function homeTactics(): array
    {
        return [
            'balanced formation and mentality gets no bonus' => [FormationTendency::BALANCED, Mentality::BALANCED, 1.46, 1.2],
            'attacking formation with defensive mentality cancels out' => [FormationTendency::ATTACKING, Mentality::DEFENSIVE, 1.46, 1.2],
            'attacking formation scores and concedes more' => [FormationTendency::ATTACKING, Mentality::BALANCED, 1.67, 1.34],
            'attacking formation and mentality scores and concedes the most' => [FormationTendency::ATTACKING, Mentality::ATTACKING, 1.89, 1.48],
            'defensive mentality scores and concedes less' => [FormationTendency::BALANCED, Mentality::DEFENSIVE, 1.24, 1.05],
            'defensive formation and mentality scores and concedes the least' => [FormationTendency::DEFENSIVE, Mentality::DEFENSIVE, 1.02, 0.91],
        ];
    }

    #[Test]
    #[DataProvider('homeTactics')]
    public function tactics_shift_expected_goals_for_both_sides(FormationTendency $formationTendency, Mentality $mentality, float $homeExpectedGoals, float $awayExpectedGoals): void
    {
        $home = $this->team(10, formationTendency: $formationTendency, mentality: $mentality);

        $result = (new MatchSimulationEngine)->simulate($home, $this->team(20));

        $this->assertSame(['home' => $homeExpectedGoals, 'away' => $awayExpectedGoals], $result->summary['expected_goals']);
    }

    #[Test]
    public function stronger_attack_against_weaker_defence_raises_expected_goals(): void
    {
        $result = (new MatchSimulationEngine)->simulate($this->team(10, attack: 15), $this->team(20));

        $this->assertSame(['home' => 2.67, 'away' => 1.2], $result->summary['expected_goals']);
    }

    #[Test]
    public function midfield_control_raises_own_and_lowers_opponent_expected_goals(): void
    {
        $result = (new MatchSimulationEngine)->simulate($this->team(10, midfield: 15), $this->team(20, midfield: 5));

        $this->assertSame(['home' => 1.82, 'away' => 0.9], $result->summary['expected_goals']);
    }

    #[Test]
    public function expected_goals_are_capped_for_mismatched_sides(): void
    {
        $result = (new MatchSimulationEngine)->simulate(
            $this->team(10, attack: 20),
            $this->team(20, attack: 1, defence: 1, goalkeeping: 1),
        );

        $this->assertSame(['home' => 6.0, 'away' => 0.05], $result->summary['expected_goals']);
    }

    #[Test]
    public function average_goals_over_many_matches_follow_expected_goals(): void
    {
        $engine = new MatchSimulationEngine(new Randomizer(new Mt19937(42)));
        $homeGoals = 0;
        $awayGoals = 0;

        for ($match = 0; $match < 2000; $match++) {
            $result = $engine->simulate($this->team(10), $this->team(20));
            $homeGoals += $result->homeGoals;
            $awayGoals += $result->awayGoals;
        }

        $this->assertEqualsWithDelta(1.46, $homeGoals / 2000, 0.1);
        $this->assertEqualsWithDelta(1.2, $awayGoals / 2000, 0.1);
    }

    #[Test]
    public function generates_one_ordered_goal_event_per_goal(): void
    {
        $engine = new MatchSimulationEngine(new Randomizer(new Mt19937(7)));

        for ($match = 0; $match < 100; $match++) {
            $result = $engine->simulate($this->team(10, attack: 14), $this->team(20, attack: 14));
            $events = $result->summary['events'];

            $this->assertSame(2, $result->summary['engine_version']);
            $this->assertSame($result->homeGoals, count(array_filter($events, fn (array $event): bool => $event['team'] === 'home' && $event['club_id'] === 10)));
            $this->assertSame($result->awayGoals, count(array_filter($events, fn (array $event): bool => $event['team'] === 'away' && $event['club_id'] === 20)));

            $minutes = array_column($events, 'minute');
            $sortedMinutes = $minutes;
            sort($sortedMinutes);
            $this->assertSame($sortedMinutes, $minutes);

            foreach ($events as $event) {
                $this->assertSame('goal', $event['type']);
                $this->assertGreaterThanOrEqual(1, $event['minute']);
                $this->assertLessThanOrEqual(90, $event['minute']);
            }
        }
    }

    private function team(
        int $clubId,
        float $attack = 10,
        float $midfield = 10,
        float $defence = 10,
        float $goalkeeping = 10,
        FormationTendency $formationTendency = FormationTendency::BALANCED,
        Mentality $mentality = Mentality::BALANCED,
    ): MatchTeam {
        return new MatchTeam($clubId, $attack, $midfield, $defence, $goalkeeping, $formationTendency, $mentality);
    }
}
