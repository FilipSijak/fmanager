<?php

namespace App\Services\CompetitionService\Competitions;

use App\Models\Club;
use App\Models\Game;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class Tournament
{
    private const MIN_PARTICIPANTS = 4;

    private const MAX_PARTICIPANTS = 32;

    private array $bracket;

    public function __construct(
        private readonly TournamentConfig $config = new TournamentConfig
    ) {}

    public function createTournament(Collection|array $clubs, int $instanceId = 1, int $seasonId = 1): array
    {
        $clubIds = collect($clubs)
            ->map(fn ($club): int => is_object($club) ? (int) $club->id : (int) $club)
            ->values()
            ->all();

        $this->validateParticipants($clubIds);

        $clubCount = count($clubIds);
        $halfSize = intdiv($clubCount, 2);
        $sideRoundCount = (int) log($clubCount, 2) - 1;

        $this->bracket = [
            'first_group' => [
                'num_rounds' => $sideRoundCount,
                'rounds' => [],
            ],
            'second_group' => [
                'num_rounds' => $sideRoundCount,
                'rounds' => [],
            ],
            'winner' => null,
            'second_placed' => null,
            'third_placed' => null,
            'finals_match' => null,
            'third_place_match' => null,
            'instance_id' => $instanceId,
            'season_id' => $seasonId,
        ];

        for ($round = 1; $round <= $sideRoundCount; $round++) {
            $this->bracket['first_group']['rounds'][$round] = ['pairs' => []];
            $this->bracket['second_group']['rounds'][$round] = ['pairs' => []];
        }

        for ($index = 0, $opponent = $clubCount - 1; $index < $halfSize; $index++, $opponent--) {
            $pair = $this->makePairMatches($clubIds[$index], $clubIds[$opponent]);
            $side = $index < intdiv($halfSize, 2) ? 'first_group' : 'second_group';
            $this->bracket[$side]['rounds'][1]['pairs'][] = $pair;
        }

        return $this->bracket;
    }

    public function setTournamentFixtures(
        int $instanceId,
        int $seasonId,
        array $schedule,
        int $competitionId,
        string|\DateTimeInterface $startDate
    ): array {
        $firstLegDate = $this->config->firstTuesdayOnOrAfter($startDate);
        $secondLegDate = $this->config->getSecondLegDate($firstLegDate);
        $firstRoundPairs = array_merge(
            $schedule['first_group']['rounds'][1]['pairs'],
            $schedule['second_group']['rounds'][1]['pairs']
        );

        foreach ($firstRoundPairs as $pair) {
            $firstGame = new Game;
            $firstGame->instance_id = $instanceId;
            $firstGame->season_id = $seasonId;
            $firstGame->competition_id = $competitionId;
            $firstGame->hometeam_id = $pair->match1->homeTeamId;
            $firstGame->awayteam_id = $pair->match1->awayTeamId;
            $firstGame->match_start = $firstLegDate;
            $firstGame->stadium_id = Club::query()->findOrFail($pair->match1->homeTeamId)->stadium_id;
            $firstGame->save();

            $secondGame = new Game;
            $secondGame->instance_id = $instanceId;
            $secondGame->season_id = $seasonId;
            $secondGame->competition_id = $competitionId;
            $secondGame->hometeam_id = $pair->match2->homeTeamId;
            $secondGame->awayteam_id = $pair->match2->awayTeamId;
            $secondGame->match_start = $secondLegDate;
            $secondGame->stadium_id = Club::query()->findOrFail($pair->match2->homeTeamId)->stadium_id;
            $secondGame->save();

            $pair->match1Id = $firstGame->id;
            $pair->match2Id = $secondGame->id;
        }

        return $schedule;
    }

    public function setNextRoundPairs(array $clubs): array
    {
        $clubIds = array_map('intval', array_values($clubs));
        $clubCount = count($clubIds);

        if ($clubCount < 2 || $clubCount % 2 !== 0) {
            throw new InvalidArgumentException('A knockout round requires an even number of at least two clubs.');
        }

        if (count(array_unique($clubIds)) !== $clubCount) {
            throw new InvalidArgumentException('Duplicate knockout round clubs are not allowed.');
        }

        $pairs = [];
        $halfSize = intdiv($clubCount, 2);

        for ($index = 0, $opponent = count($clubIds) - 1; $index < $halfSize; $index++, $opponent--) {
            $pairs[] = $this->makePairMatches($clubIds[$index], $clubIds[$opponent]);
        }

        return $pairs;
    }

    public function createTournamentGroups(array $clubs): array
    {
        if (count($clubs) === 0 || count($clubs) % 4 !== 0) {
            throw new InvalidArgumentException('Tournament group stages require a non-zero multiple of four clubs.');
        }

        $clubsByGroup = [];

        foreach (array_values($clubs) as $index => $club) {
            $groupId = intdiv($index, 4) + 1;
            $clubsByGroup[$groupId][] = (int) $club['id'];
        }

        foreach ($clubsByGroup as &$group) {
            shuffle($group);
        }

        return $clubsByGroup;
    }

    private function validateParticipants(array $clubIds): void
    {
        $count = count($clubIds);

        if ($count < self::MIN_PARTICIPANTS || $count > self::MAX_PARTICIPANTS) {
            throw new InvalidArgumentException(sprintf(
                'Knockout tournaments require between %d and %d clubs; %d provided.',
                self::MIN_PARTICIPANTS,
                self::MAX_PARTICIPANTS,
                $count
            ));
        }

        if (($count & ($count - 1)) !== 0) {
            throw new InvalidArgumentException(
                "Knockout tournament club count must be a power of two; {$count} provided."
            );
        }

        if (count(array_unique($clubIds)) !== $count) {
            throw new InvalidArgumentException('Duplicate knockout tournament clubs are not allowed.');
        }
    }

    private function makePairMatches(int $firstTeamId, int $secondTeamId): \stdClass
    {
        $pair = new \stdClass;
        $pair->match1 = (object) [
            'homeTeamId' => $firstTeamId,
            'awayTeamId' => $secondTeamId,
        ];
        $pair->match2 = (object) [
            'homeTeamId' => $secondTeamId,
            'awayTeamId' => $firstTeamId,
        ];
        $pair->winner = null;
        $pair->match1Id = null;
        $pair->match2Id = null;

        return $pair;
    }
}
