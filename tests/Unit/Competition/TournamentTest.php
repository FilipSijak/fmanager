<?php

namespace Tests\Unit\Competition;

use App\Services\CompetitionService\Competitions\Tournament;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TournamentTest extends TestCase
{
    use DatabaseMigrations;

    #[Test]
    public function it_can_create_groups_from_array_of_clubs()
    {
        $tournament = new Tournament;
        $clubs = [
            0 => ['id' => 1],
            1 => ['id' => 2],
            2 => ['id' => 3],
            3 => ['id' => 4],
            4 => ['id' => 5],
            5 => ['id' => 6],
            6 => ['id' => 7],
            7 => ['id' => 8],
        ];

        $clubsByGroups = $tournament->createTournamentGroups($clubs);

        $this->assertEquals(2, count($clubsByGroups));
        $this->assertEquals(4, count($clubsByGroups[1]));
    }

    #[Test]
    public function it_rejects_non_power_of_two_participant_counts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a power of two');

        (new Tournament)->createTournament(range(1, 6));
    }

    #[Test]
    public function it_can_create_a_tournament()
    {
        $tournament = new Tournament;
        $clubs = [
            0 => (object) ['id' => 1],
            1 => (object) ['id' => 2],
            2 => (object) ['id' => 3],
            3 => (object) ['id' => 4],
            4 => (object) ['id' => 5],
            5 => (object) ['id' => 6],
            6 => (object) ['id' => 7],
            7 => (object) ['id' => 8],
        ];

        $summary = $tournament->createTournament($clubs);

        $this->assertEquals(2, $summary['first_group']['num_rounds']);
        $this->assertEquals(2, count($summary['first_group']['rounds'][1]['pairs']));
        $this->assertEquals(2, count($summary['second_group']['rounds'][1]['pairs']));
    }
}
