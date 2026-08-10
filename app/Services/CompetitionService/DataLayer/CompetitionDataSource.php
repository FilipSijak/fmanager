<?php

namespace App\Services\CompetitionService\DataLayer;

use App\Models\Club;
use Illuminate\Support\Facades\DB;

class CompetitionDataSource
{
    public function storeLeagueScheduleFixtures(array $fixtures, int $competitionId, int $seasonId, int $instanceId): void
    {
        $this->insertGameFixtures($fixtures, $competitionId, $seasonId, $instanceId);
    }

    public function storeTournamentGroupScheduleFixtures(array $fixtures, int $competitionId, int $seasonId, int $instanceId): void
    {
        $this->insertGameFixtures($fixtures, $competitionId, $seasonId, $instanceId);
    }

    private function insertGameFixtures(array $fixtures, int $competitionId, int $seasonId, int $instanceId): void
    {
        $clubStadiums = Club::query()
            ->where('instance_id', $instanceId)
            ->whereIn('id', collect($fixtures)->pluck('home_club_id')->unique()->all())
            ->pluck('stadium_id', 'id');
        $rows = [];

        foreach ($fixtures as $fixture) {
            $homeClubId = (int) $fixture['home_club_id'];
            if (! isset($clubStadiums[$homeClubId])) {
                throw new \UnexpectedValueException(
                    "Unable to schedule fixture: home club {$homeClubId} has no stadium for instance {$instanceId}."
                );
            }
            $rows[] = [
                'instance_id' => $instanceId,
                'season_id' => $seasonId,
                'competition_id' => $competitionId,
                'hometeam_id' => $homeClubId,
                'awayteam_id' => (int) $fixture['away_club_id'],
                'stadium_id' => (int) $clubStadiums[$homeClubId],
                'match_start' => $fixture['date']->format('Y-m-d H:i:s'),
            ];
        }

        DB::table('games')->insert($rows);
    }

    public function storeInitialCompetitionSeasonClubs(int $instanceId, int $seasonId): void
    {
        $rows = DB::table('base_clubs AS bc')
            ->join('clubs AS c', function ($join) use ($instanceId) {
                $join->on('c.base_club_id', '=', 'bc.id')->where('c.instance_id', '=', $instanceId);
            })
            ->join('competitions AS comp', function ($join) use ($instanceId) {
                $join->on('comp.base_competition_id', '=', 'bc.competition_id')->where('comp.instance_id', '=', $instanceId);
            })
            ->select([
                DB::raw((int) $instanceId.' AS instance_id'),
                'comp.id AS competition_id',
                DB::raw((int) $seasonId.' AS season_id'),
                'c.id AS club_id',
                DB::raw("CASE WHEN comp.type = 'tournament' AND comp.groups = 1 THEN 1 ELSE 0 END AS groups_active"),
                DB::raw('0 AS points'),
            ])
            ->get()->map(fn ($row) => (array) $row)->all();

        DB::table('competition_season')->insert($rows);
        $this->storeInitialContinentalCompetitionClubs($instanceId, $seasonId);
    }

    private function storeInitialContinentalCompetitionClubs(int $instanceId, int $seasonId): void
    {
        $majorLeagueIds = DB::table('competition_progression_rules')
            ->where('progression_type', 'continental')
            ->where('active', true)
            ->distinct()
            ->orderBy('source_base_competition_id')
            ->pluck('source_base_competition_id');

        $clubsByCountry = DB::table('clubs AS club')
            ->join('base_clubs AS base_club', 'base_club.id', '=', 'club.base_club_id')
            ->where('club.instance_id', $instanceId)
            ->whereIn('base_club.competition_id', $majorLeagueIds)
            ->select('club.id', 'club.country_code', 'club.rank', 'club.base_club_id')
            ->orderBy('club.country_code')
            ->orderByDesc('club.rank')
            ->orderBy('club.base_club_id')
            ->get()
            ->groupBy('country_code');

        $continentalCompetitions = DB::table('competitions')
            ->where('instance_id', $instanceId)
            ->where('competition_scope', 'continental')
            ->orderBy('continental_tier')
            ->orderBy('id')
            ->get();

        $countryOffsets = $clubsByCountry->mapWithKeys(fn ($clubs, $country): array => [$country => 0])->all();

        foreach ($continentalCompetitions as $competition) {
            $selected = [];

            while (count($selected) < (int) $competition->clubs_number) {
                $clubsAddedThisPass = 0;

                foreach ($clubsByCountry as $country => $clubs) {
                    if (count($selected) === (int) $competition->clubs_number) {
                        break;
                    }

                    $offset = $countryOffsets[$country];
                    if (! isset($clubs[$offset])) {
                        continue;
                    }

                    $selected[] = (int) $clubs[$offset]->id;
                    $countryOffsets[$country]++;
                    $clubsAddedThisPass++;
                }

                if ($clubsAddedThisPass === 0) {
                    throw new \UnexpectedValueException(
                        "Not enough ranked major-league clubs to populate {$competition->name}."
                    );
                }
            }

            DB::table('competition_season')->insert(array_map(
                fn (int $clubId): array => [
                    'instance_id' => $instanceId,
                    'competition_id' => (int) $competition->id,
                    'season_id' => $seasonId,
                    'club_id' => $clubId,
                    'groups_active' => (int) $competition->groups === 1,
                ],
                $selected
            ));
        }
    }

    public function storeTournamentKnockoutSchedule(
        int $instanceId,
        int $competitionId,
        int $seasonId,
        array $schedule
    ): int {
        return DB::transaction(function () use ($instanceId, $competitionId, $seasonId, $schedule): int {
            $sides = ['first_group' => 'first', 'second_group' => 'second'];
            $numberOfRounds = (int) $schedule['first_group']['num_rounds'];
            $firstRoundPairs = [];

            foreach ($sides as $scheduleKey => $side) {
                foreach ($schedule[$scheduleKey]['rounds'][1]['pairs'] as $pair) {
                    $firstRoundPairs[] = ['side' => $side, 'pair' => (array) $pair];
                }
            }

            $participants = collect($firstRoundPairs)->flatMap(function (array $entry): array {
                $pair = $entry['pair'];

                return [(int) $pair['match1']->homeTeamId, (int) $pair['match1']->awayTeamId];
            })->unique()->values();

            $knockoutId = DB::table('tournament_knockout')->insertGetId([
                'instance_id' => $instanceId,
                'competition_id' => $competitionId,
                'season_id' => $seasonId,
                'participant_count' => $participants->count(),
                'bracket_size' => $this->nextPowerOfTwo($participants->count()),
                'status' => 'in_progress',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('tournament_knockout_participants')->insert(
                $participants->map(fn (int $clubId, int $seed): array => [
                    'tournament_knockout_id' => $knockoutId,
                    'club_id' => $clubId,
                    'seed' => $seed + 1,
                ])->all()
            );

            $roundIds = [];
            $tieIds = [];
            $initialPairsPerSide = count($schedule['first_group']['rounds'][1]['pairs']);

            foreach (['first', 'second'] as $side) {
                for ($roundNumber = 1; $roundNumber <= $numberOfRounds; $roundNumber++) {
                    $roundIds[$side][$roundNumber] = DB::table('tournament_knockout_rounds')->insertGetId([
                        'tournament_knockout_id' => $knockoutId,
                        'round_number' => $roundNumber,
                        'bracket_side' => $side,
                        'name' => $this->roundName(
                            (int) ($participants->count() / (2 ** ($roundNumber - 1)))
                        ),
                        'number_of_legs' => 2,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $tieCount = max(1, (int) ($initialPairsPerSide / (2 ** ($roundNumber - 1))));
                    for ($position = 1; $position <= $tieCount; $position++) {
                        $tieIds[$side][$roundNumber][$position] = DB::table('tournament_knockout_ties')->insertGetId([
                            'round_id' => $roundIds[$side][$roundNumber],
                            'position' => $position,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            $finalRoundId = DB::table('tournament_knockout_rounds')->insertGetId([
                'tournament_knockout_id' => $knockoutId,
                'round_number' => $numberOfRounds + 1,
                'bracket_side' => 'final',
                'name' => 'final',
                'number_of_legs' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $finalTieId = DB::table('tournament_knockout_ties')->insertGetId([
                'round_id' => $finalRoundId,
                'position' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($sides as $scheduleKey => $side) {
                foreach ($schedule[$scheduleKey]['rounds'][1]['pairs'] as $index => $pairObject) {
                    $pair = (array) $pairObject;
                    $tieId = $tieIds[$side][1][$index + 1];
                    DB::table('tournament_knockout_ties')->where('id', $tieId)->update([
                        'home_club_id' => (int) $pair['match1']->homeTeamId,
                        'away_club_id' => (int) $pair['match1']->awayTeamId,
                        'status' => 'in_progress',
                        'updated_at' => now(),
                    ]);
                    DB::table('games')->where('id', $pair['match1Id'])->update(['knockout_tie_id' => $tieId, 'leg_number' => 1]);
                    DB::table('games')->where('id', $pair['match2Id'])->update(['knockout_tie_id' => $tieId, 'leg_number' => 2]);
                }

                for ($roundNumber = 1; $roundNumber <= $numberOfRounds; $roundNumber++) {
                    foreach ($tieIds[$side][$roundNumber] as $position => $tieId) {
                        if ($roundNumber === $numberOfRounds) {
                            $nextTieId = $finalTieId;
                            $nextSlot = $side === 'first' ? 'home' : 'away';
                        } else {
                            $nextTieId = $tieIds[$side][$roundNumber + 1][(int) ceil($position / 2)];
                            $nextSlot = $position % 2 === 1 ? 'home' : 'away';
                        }
                        DB::table('tournament_knockout_ties')->where('id', $tieId)->update([
                            'next_tie_id' => $nextTieId,
                            'next_tie_slot' => $nextSlot,
                        ]);
                    }
                }
            }

            return $knockoutId;
        });
    }

    public function insertTournamentGroups(int $instanceId, array $groups, int $competitionId, int $seasonId): void
    {
        $rows = [];
        foreach ($groups as $groupId => $group) {
            foreach ($group as $clubId) {
                $rows[] = [
                    'instance_id' => $instanceId,
                    'competition_id' => $competitionId,
                    'season_id' => $seasonId,
                    'group_id' => (int) $groupId,
                    'club_id' => (int) $clubId,
                ];
            }
        }
        foreach ($rows as $row) {
            DB::table('competition_season')->updateOrInsert(
                [
                    'instance_id' => $row['instance_id'],
                    'competition_id' => $row['competition_id'],
                    'season_id' => $row['season_id'],
                    'club_id' => $row['club_id'],
                ],
                [
                    'group_id' => $row['group_id'],
                    'groups_active' => true,
                ]
            );
        }
    }

    private function nextPowerOfTwo(int $number): int
    {
        $power = 1;
        while ($power < $number) {
            $power *= 2;
        }

        return $power;
    }

    private function roundName(int $clubsRemaining): string
    {
        return match ($clubsRemaining) {
            4 => 'semi_final',
            8 => 'quarter_final',
            16 => 'round_of_16',
            32 => 'round_of_32',
            default => 'round_of_'.$clubsRemaining,
        };
    }
}
