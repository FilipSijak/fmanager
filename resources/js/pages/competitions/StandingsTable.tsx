import type { Standing } from './types';

export default function StandingsTable({
    rows,
    clubId,
}: {
    rows: Standing[];
    clubId: number | null;
}) {
    if (rows.length === 0) {
        return (
            <p className="p-6 text-center text-white/70">
                No standings are available yet.
            </p>
        );
    }

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
                                {row.club_name}
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
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
