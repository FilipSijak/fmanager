<?php

namespace App\Services\CompetitionService\Competitions;

use App\Repositories\GameRepository;

class KnockoutSummaryRoundsData
{
    private GameRepository $gameRepository;

    public function __construct(GameRepository $gameRepository)
    {
        $this->gameRepository = $gameRepository;
    }

    /** Gets data needed for all the related games within a specific round */
    public function getCurrentRound(string $knockoutSummary): array | int
    {
        $knockoutSummary = json_decode($knockoutSummary, true);

        if ($knockoutSummary['finals_match']) {
            return $knockoutSummary['finals_match'];
        }

        $firstGroupRounds = $knockoutSummary['first_group']['rounds'];
        $secondGroupRounds = $knockoutSummary['second_group']['rounds'];
        $bothGroups = [];

        for ($i = 1; $i <= $knockoutSummary['first_group']['num_rounds']; $i++) {
            if (!isset($firstGroupRounds[$i + 1]) || empty($firstGroupRounds[$i + 1]['pairs'])) {
                $bothGroups['first_group'] = (array) $firstGroupRounds[$i]['pairs'];
                break;
            }
        }

        for ($i = 1; $i <= $knockoutSummary['first_group']['num_rounds']; $i++) {
            if (!isset($secondGroupRounds[$i + 1]) || empty($secondGroupRounds[$i + 1]['pairs'])) {
                $bothGroups['second_group'] = (array) $secondGroupRounds[$i]['pairs'];
                break;
            }
        }

        return $bothGroups;
    }

    /** Returns knockout data only for the view layer
     * (List of knockout matches to be played on the day and their return fixture).
     */
    public function displayCurrentRound(string $knockoutSummary): array
    {
        $roundPairsRaw = $this->getCurrentRound($knockoutSummary);

        $presentationData = [];

        foreach ($roundPairsRaw['first_group'] as $pair) {
            $presentationData[] = $this->getPairRoundFullInfo($pair);
        }

        foreach ($roundPairsRaw['second_group'] as $pair) {
            $presentationData[] = $this->getPairRoundFullInfo($pair);;
        }

        return $presentationData;
    }

    /**
     * Returns knockout data only for the view layer.
     * Frontend table for the tournament can be created from this
     */
    public function displayAllRounds(string $summary): array
    {

        $summary = (array) json_decode($summary, true);

        $numRounds = $summary['first_group']['num_rounds'];
        $parsedCompetitionView = [];
        $parsedCompetitionView['first_group'] = [];
        $parsedCompetitionView['second_group'] = [];

        for ($i = 1; $i <= $numRounds; $i++) {
            $parsedCompetitionView['first_group'][$i]['pairs'] = [];
            $parsedCompetitionView['second_group'][$i]['pairs'] = [];

            foreach ($summary['first_group']['rounds'][$i]['pairs'] as $pair) {
                $parsedCompetitionView['first_group'][$i]['pairs'][] = $this->getPairRoundFullInfo($pair);
            }

            foreach ($summary['second_group']['rounds'][$i]['pairs'] as $pair) {
                $parsedCompetitionView['second_group'][$i]['pairs'][] = $this->getPairRoundFullInfo($pair);
            }
        }

        return $parsedCompetitionView;
    }

    private function getPairRoundFullInfo(array $pair): array
    {
        return [
            'winner' => $pair['winner'],
            'game1' => $this->gameRepository->getFullGameData($pair['match1Id']),
            'game2' => $this->gameRepository->getFullGameData($pair['match2Id']),
        ];
    }
}
