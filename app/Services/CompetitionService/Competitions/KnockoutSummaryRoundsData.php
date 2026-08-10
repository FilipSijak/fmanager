<?php

namespace App\Services\CompetitionService\Competitions;

use App\Repositories\GameRepository;

class KnockoutSummaryRoundsData
{
    public function __construct(private readonly GameRepository $gameRepository) {}

    public function getCurrentRound(string $knockoutSummary): array|int
    {
        $summary = json_decode($knockoutSummary, true, flags: JSON_THROW_ON_ERROR);

        if ($summary['finals_match']) {
            return (int) $summary['finals_match'];
        }

        $current = [
            'first_group' => [],
            'second_group' => [],
        ];

        $firstRound = $summary['first_group']['rounds'][1] ?? null;
        if ($firstRound !== null && ! array_key_exists('status', $firstRound)) {
            return $this->getLegacyCurrentRound($summary);
        }

        foreach (['first_group', 'second_group'] as $side) {
            foreach ($summary[$side]['rounds'] as $round) {
                $scheduledPairs = array_values(array_filter(
                    $round['pairs'],
                    static fn (array $pair): bool => $pair['match1Id'] !== null
                ));

                $roundCompleted = ($round['status'] ?? null) === 'completed'
                    || (! array_key_exists('status', $round)
                        && $round['pairs'] !== []
                        && collect($round['pairs'])->every(
                            static fn (array $pair): bool => $pair['winner'] !== null
                        ));

                if (! $roundCompleted && $scheduledPairs !== []) {
                    $current[$side] = $scheduledPairs;
                    break;
                }
            }
        }

        return $current;
    }

    private function getLegacyCurrentRound(array $summary): array
    {
        $current = [
            'first_group' => [],
            'second_group' => [],
        ];

        foreach (['first_group', 'second_group'] as $side) {
            $rounds = $summary[$side]['rounds'];
            $roundCount = (int) $summary[$side]['num_rounds'];

            for ($round = 1; $round <= $roundCount; $round++) {
                if (! isset($rounds[$round + 1]) || $rounds[$round + 1]['pairs'] === []) {
                    $current[$side] = $rounds[$round]['pairs'];
                    break;
                }
            }
        }

        return $current;
    }

    public function displayCurrentRound(string $knockoutSummary): array
    {
        $currentRound = $this->getCurrentRound($knockoutSummary);

        if (is_int($currentRound)) {
            return [$this->gameRepository->getFullGameData($currentRound)];
        }

        $presentationData = [];

        foreach (['first_group', 'second_group'] as $side) {
            foreach ($currentRound[$side] as $pair) {
                $presentationData[] = $this->getPairRoundFullInfo($pair);
            }
        }

        return $presentationData;
    }

    public function displayAllRounds(string $summary): array
    {
        $summary = json_decode($summary, true, flags: JSON_THROW_ON_ERROR);
        $parsedCompetitionView = [
            'first_group' => [],
            'second_group' => [],
        ];

        foreach (['first_group', 'second_group'] as $side) {
            foreach ($summary[$side]['rounds'] as $roundNumber => $round) {
                $parsedCompetitionView[$side][$roundNumber] = [
                    'id' => $round['id'] ?? null,
                    'name' => $round['name'] ?? null,
                    'status' => $round['status'] ?? null,
                    'pairs' => array_map(
                        fn (array $pair): array => $this->getPairRoundFullInfo($pair),
                        $round['pairs']
                    ),
                ];
            }
        }

        return $parsedCompetitionView;
    }

    private function getPairRoundFullInfo(array $pair): array
    {
        return [
            'winner' => $pair['winner'],
            'game1' => $pair['match1Id'] === null
                ? null
                : $this->gameRepository->getFullGameData($pair['match1Id']),
            'game2' => $pair['match2Id'] === null
                ? null
                : $this->gameRepository->getFullGameData($pair['match2Id']),
        ];
    }
}
