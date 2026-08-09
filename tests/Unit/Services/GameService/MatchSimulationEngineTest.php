<?php

namespace Tests\Unit\Services\GameService;

use App\Models\Game;
use App\Services\GameService\MatchSimulationEngine;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MatchSimulationEngineTest extends TestCase
{
    #[Test]
    public function it_generates_a_valid_score_and_matching_ordered_goal_events(): void
    {
        $game = new Game();
        $game->hometeam_id = 10;
        $game->awayteam_id = 20;
        $engine = new MatchSimulationEngine();

        for ($simulation = 0; $simulation < 100; $simulation++) {
            $result = $engine->simulate($game);
            $events = $result->summary['events'];

            $this->assertGreaterThanOrEqual(0, $result->homeGoals);
            $this->assertLessThanOrEqual(4, $result->homeGoals);
            $this->assertGreaterThanOrEqual(0, $result->awayGoals);
            $this->assertLessThanOrEqual(4, $result->awayGoals);
            $this->assertSame($result->homeGoals + $result->awayGoals, count($events));
            $this->assertSame(1, $result->summary['engine_version']);

            $minutes = array_column($events, 'minute');
            $sortedMinutes = $minutes;
            sort($sortedMinutes);
            $this->assertSame($sortedMinutes, $minutes);

            foreach ($events as $event) {
                $this->assertSame('goal', $event['type']);
                $this->assertContains($event['team'], ['home', 'away']);
                $this->assertSame($event['team'] === 'home' ? 10 : 20, $event['club_id']);
                $this->assertGreaterThanOrEqual(1, $event['minute']);
                $this->assertLessThanOrEqual(90, $event['minute']);
            }
        }
    }
}
