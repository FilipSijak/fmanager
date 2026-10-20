import { Link } from '@inertiajs/react';
import { show as showMatch } from '@/routes/matches';
import type { CompetitionMatch } from './types';

export function matchDate(value: string | null): string {
    if (!value) return 'Date to be confirmed';
    return new Intl.DateTimeFormat('en-GB', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${value.slice(0, 10)}T12:00:00`));
}

export default function MatchList({
    matches,
    clubId,
    emptyMessage,
}: {
    matches: CompetitionMatch[];
    clubId: number | null;
    emptyMessage: string;
}) {
    if (matches.length === 0) {
        return <p className="p-8 text-center text-white/70">{emptyMessage}</p>;
    }

    return (
        <ul className="divide-y divide-[#2a2f1a]">
            {matches.map((match) => {
                const completed = match.status === 'completed';
                const managed =
                    match.hometeam_id === clubId ||
                    match.awayteam_id === clubId;
                return (
                    <li
                        key={match.id}
                        className={`grid grid-cols-[180px_1fr_90px_1fr_110px] items-center gap-3 px-4 py-3 text-sm ${managed ? 'bg-[#101358]' : 'bg-[#0c0c14] even:bg-white/5'}`}
                    >
                        <time
                            dateTime={match.match_start?.replace(' ', 'T')}
                            className="text-white/70"
                        >
                            {matchDate(match.match_start)}
                        </time>
                        <span
                            className={`text-right ${match.hometeam_id === clubId ? 'font-bold text-[#f5f000]' : ''}`}
                        >
                            {match.home_team}
                        </span>
                        <Link
                            href={showMatch.url(match.id)}
                            aria-label={`View ${match.home_team} versus ${match.away_team}`}
                            className="text-center font-bold underline decoration-white/40 underline-offset-4 tabular-nums hover:text-[#f5f000]"
                        >
                            {completed
                                ? `${match.home_team_goals ?? '–'} – ${match.away_team_goals ?? '–'}`
                                : ['cancelled', 'abandoned'].includes(
                                        match.status,
                                    )
                                  ? '—'
                                  : match.match_start?.slice(11, 16) || 'v'}
                        </Link>
                        <span
                            className={
                                match.awayteam_id === clubId
                                    ? 'font-bold text-[#f5f000]'
                                    : ''
                            }
                        >
                            {match.away_team}
                        </span>
                        <span className="text-right text-xs text-white/60 capitalize">
                            {match.status.replaceAll('_', ' ')}
                            {match.leg_number !== null &&
                                ` · Leg ${match.leg_number}`}
                            {match.group_id !== null &&
                                ` · Group ${match.group_id}`}
                        </span>
                    </li>
                );
            })}
        </ul>
    );
}
