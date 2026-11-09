import { Link } from '@inertiajs/react';
import { show as matchReport } from '@/routes/matches';
import type { PlayerProfileData } from '../types';

export default function RecentAppearances({
    appearances,
}: {
    appearances: PlayerProfileData['performance']['recent'];
}) {
    return (
        <section
            className="mt-5 overflow-x-auto"
            aria-label="Recent appearances"
        >
            <h2 className="mb-2 font-bold text-[#f5f000]">
                Last five appearances
            </h2>
            {appearances.length === 0 ? (
                <p className="py-3 text-sm text-white">
                    No recent appearances recorded.
                </p>
            ) : (
                <table className="w-full text-left text-sm text-white">
                    <thead className="bg-[#c9c9cc] text-slate-900">
                        <tr>
                            {[
                                'Date',
                                'Opponent',
                                'Venue',
                                'Score (home–away)',
                                'Minutes',
                                'Goals',
                                'Assists',
                                'Rating',
                            ].map((label) => (
                                <th
                                    key={label}
                                    scope="col"
                                    className="px-3 py-2"
                                >
                                    {label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {appearances.map((match) => (
                            <tr
                                key={match.game_id}
                                className="border-b border-white/10"
                            >
                                <td className="whitespace-nowrap px-3 py-2">
                                    {match.date}
                                </td>
                                <th scope="row" className="px-3 py-2">
                                    <Link
                                        href={matchReport.url(match.game_id)}
                                        className="text-[#f5f000] underline hover:text-white"
                                    >
                                        {match.opponent}
                                    </Link>
                                </th>
                                <td className="px-3 py-2">{match.venue}</td>
                                <td className="px-3 py-2">
                                    {match.home_goals === null ||
                                    match.away_goals === null
                                        ? '—'
                                        : `${match.home_goals}–${match.away_goals}`}
                                </td>
                                <td className="px-3 py-2">{match.minutes}</td>
                                <td className="px-3 py-2">{match.goals}</td>
                                <td className="px-3 py-2">{match.assists}</td>
                                <td className="px-3 py-2">
                                    {match.rating.toFixed(1)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </section>
    );
}
