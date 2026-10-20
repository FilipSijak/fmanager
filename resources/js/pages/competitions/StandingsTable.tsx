import ClubLink from '@/components/game/ClubLink';
import type { Standing } from './types';

const outcomeLabels = {
    promotion: 'Promotion',
    relegation: 'Relegation',
    continental: 'Qualification',
    knockout: 'Qualification',
};

export default function StandingsTable({
    rows,
    clubId,
    provisional = true,
    seasonId,
}: {
    rows: Standing[];
    clubId: number | null;
    provisional?: boolean;
    seasonId?: number;
}) {
    if (rows.length === 0) {
        return (
            <p className="p-6 text-center text-white/70">
                No standings are available yet.
            </p>
        );
    }
    const hasOutcomes = rows.some((row) => row.outcomes.length > 0);

    return (
        <div className="overflow-x-auto">
            <table className="w-full text-right text-sm">
                <caption className="sr-only">Competition standings</caption>
                <thead className="bg-[#101358] text-white">
                    <tr>
                        {[
                            'Pos',
                            'Club',
                            'P',
                            'W',
                            'D',
                            'L',
                            'GF',
                            'GA',
                            'GD',
                            'Pts',
                        ].map((heading) => (
                            <th
                                key={heading}
                                scope="col"
                                className={`px-3 py-3 ${heading === 'Club' ? 'w-full text-left' : ''}`}
                            >
                                <abbr
                                    className="no-underline"
                                    title={
                                        {
                                            Pos: 'Position',
                                            Club: 'Club',
                                            P: 'Played',
                                            W: 'Won',
                                            D: 'Drawn',
                                            L: 'Lost',
                                            GF: 'Goals for',
                                            GA: 'Goals against',
                                            GD: 'Goal difference',
                                            Pts: 'Points',
                                        }[heading]
                                    }
                                >
                                    {heading}
                                </abbr>
                            </th>
                        ))}
                        {hasOutcomes && (
                            <th scope="col" className="px-3 py-3 text-left">
                                Places
                            </th>
                        )}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr
                            key={row.club_id}
                            className={
                                row.club_id === clubId
                                    ? 'bg-[#a41c1c] font-bold text-white'
                                    : 'bg-white text-slate-900 even:bg-[#d9d9d9]'
                            }
                        >
                            <td className="px-3 py-2">{row.position}</td>
                            <th
                                scope="row"
                                className="px-3 py-2 text-left whitespace-nowrap"
                            >
                                <ClubLink
                                    clubId={row.club_id}
                                    name={row.club_name}
                                    seasonId={seasonId}
                                />
                                {row.club_id === clubId && (
                                    <span className="sr-only">
                                        {' '}
                                        (your club)
                                    </span>
                                )}
                            </th>
                            {(
                                [
                                    'played',
                                    'wins',
                                    'draws',
                                    'losses',
                                    'goals_for',
                                    'goals_against',
                                ] as const
                            ).map((field) => (
                                <td
                                    key={field}
                                    className="px-3 py-2 tabular-nums"
                                >
                                    {row[field]}
                                </td>
                            ))}
                            <td className="px-3 py-2 tabular-nums">
                                {row.goal_difference > 0 ? '+' : ''}
                                {row.goal_difference}
                            </td>
                            <td className="px-3 py-2 font-black tabular-nums">
                                {row.points}
                            </td>
                            {hasOutcomes && (
                                <td className="px-3 py-2 text-left">
                                    <div className="flex flex-wrap gap-1">
                                        {row.outcomes.map((outcome) => (
                                            <span
                                                key={`${outcome.type}-${outcome.target_name}`}
                                                className={`inline-block rounded px-2 py-1 text-xs font-bold whitespace-nowrap ${outcome.type === 'relegation' ? 'bg-red-100 text-red-900' : outcome.type === 'promotion' ? 'bg-emerald-100 text-emerald-900' : 'bg-sky-100 text-sky-900'}`}
                                                title={outcome.entry_stage?.replaceAll(
                                                    '_',
                                                    ' ',
                                                )}
                                            >
                                                {outcomeLabels[outcome.type]} ·{' '}
                                                {outcome.target_name}
                                                {outcome.entry_stage &&
                                                    ` (${outcome.entry_stage.replaceAll('_', ' ')})`}
                                            </span>
                                        ))}
                                    </div>
                                </td>
                            )}
                        </tr>
                    ))}
                </tbody>
            </table>
            {hasOutcomes && (
                <p className="bg-[#101358] px-4 py-3 text-left text-xs text-white/70">
                    {provisional
                        ? 'Promotion and qualification places are provisional until the competition finishes.'
                        : 'Promotion and qualification places for this season.'}
                </p>
            )}
        </div>
    );
}
