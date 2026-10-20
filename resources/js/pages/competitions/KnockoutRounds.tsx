import MatchList from './MatchList';
import type { CompetitionMatch, KnockoutRound } from './types';

export function roundName(name: string): string {
    return name
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

export default function KnockoutRounds({
    rounds,
    matches,
    clubId,
    seasonId,
}: {
    rounds: KnockoutRound[];
    matches: CompetitionMatch[];
    clubId: number | null;
    seasonId?: number;
}) {
    if (rounds.length === 0) {
        return (
            <p className="p-8 text-center text-white/70">
                The knockout draw has not been made yet.
            </p>
        );
    }

    const stages = new Map<number, KnockoutRound[]>();
    for (const round of rounds) {
        stages.set(round.round_number, [
            ...(stages.get(round.round_number) ?? []),
            round,
        ]);
    }

    return (
        <div className="space-y-6 p-4">
            {[...stages.entries()].map(([number, stage]) => (
                <section
                    key={number}
                    aria-label={roundName(stage[0].name)}
                    className="overflow-hidden border border-[#3355dd]/50"
                >
                    <h2 className="bg-[#101358] px-4 py-3 font-bold text-[#f5f000]">
                        {roundName(stage[0].name)}
                    </h2>
                    <div className="divide-y divide-[#3355dd]/40">
                        {stage.flatMap((round) =>
                            round.ties.map((tie) => {
                                const games = matches
                                    .filter(
                                        (match) =>
                                            match.knockout_tie_id === tie.id,
                                    )
                                    .sort(
                                        (a, b) =>
                                            (a.leg_number ?? 0) -
                                            (b.leg_number ?? 0),
                                    );
                                const completed = games.filter(
                                    (game) => game.status === 'completed',
                                );
                                const aggregate = (club: number | null) =>
                                    completed.reduce(
                                        (total, game) =>
                                            total +
                                            (game.hometeam_id === club
                                                ? (game.home_team_goals ?? 0)
                                                : (game.away_team_goals ?? 0)),
                                        0,
                                    );
                                const winner =
                                    tie.winner_club_id === null
                                        ? null
                                        : tie.winner_club_id ===
                                            tie.home_club_id
                                          ? tie.home_team
                                          : tie.away_team;
                                return (
                                    <div key={tie.id}>
                                        <div className="flex flex-wrap items-center justify-between gap-2 bg-white/5 px-4 py-3 text-sm">
                                            <p className="font-bold">
                                                <span
                                                    className={
                                                        clubId !== null &&
                                                        tie.home_club_id ===
                                                            clubId
                                                            ? 'text-[#f5f000]'
                                                            : ''
                                                    }
                                                >
                                                    <ClubLink
                                                        clubId={
                                                            tie.home_club_id
                                                        }
                                                        name={tie.home_team}
                                                        seasonId={seasonId}
                                                    />
                                                </span>
                                                <span className="px-3 text-white/50">
                                                    v
                                                </span>
                                                <span
                                                    className={
                                                        clubId !== null &&
                                                        tie.away_club_id ===
                                                            clubId
                                                            ? 'text-[#f5f000]'
                                                            : ''
                                                    }
                                                >
                                                    <ClubLink
                                                        clubId={
                                                            tie.away_club_id
                                                        }
                                                        name={tie.away_team}
                                                        seasonId={seasonId}
                                                    />
                                                </span>
                                            </p>
                                            <p className="text-white/70">
                                                {round.number_of_legs > 1 &&
                                                    completed.length > 0 &&
                                                    `Aggregate: ${aggregate(tie.home_club_id)} – ${aggregate(tie.away_club_id)} · `}
                                                {winner
                                                    ? `${winner} ${round.bracket_side === 'final' ? 'win the cup' : 'advance'}`
                                                    : round.number_of_legs > 1
                                                      ? 'Two legs'
                                                      : 'Single match'}
                                            </p>
                                        </div>
                                        <MatchList
                                            matches={games}
                                            clubId={clubId}
                                            emptyMessage="Fixtures will appear when the draw is confirmed."
                                        />
                                    </div>
                                );
                            }),
                        )}
                    </div>
                </section>
            ))}
        </div>
    );
}

import ClubLink from '@/components/game/ClubLink';
