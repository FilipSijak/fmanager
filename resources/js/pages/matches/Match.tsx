import { Head, Link } from '@inertiajs/react';
import ClubLink from '@/components/game/ClubLink';
import GameLayout from '@/layouts/GameLayout';
import { show as showCompetition } from '@/routes/competitions';
import { matchDate } from '../competitions/MatchList';

type MatchDetails = {
    id: number;
    season_id: number;
    competition_id: number;
    competition_name: string;
    season_start: string;
    season_end: string;
    match_start: string | null;
    status: string;
    hometeam_id: number;
    awayteam_id: number;
    home_team: string;
    away_team: string;
    home_team_goals: number | null;
    away_team_goals: number | null;
    stadium_name: string | null;
    attendance: number | null;
    leg_number: number | null;
};

export default function Match({
    match,
    events,
}: {
    match: MatchDetails;
    events: {
        id: string;
        minute: number;
        type: string;
        club_id: number | null;
    }[];
}) {
    const completed = match.status === 'completed';
    const heading = `${match.home_team} v ${match.away_team}`;
    return (
        <GameLayout active="Competitions">
            <Head title={heading} />
            <header className="border-b border-[#3355dd]/50 bg-black px-6 pt-14 pb-6 text-center text-white">
                <Link
                    href={showCompetition.url(match.competition_id, {
                        query: { season_id: match.season_id },
                    })}
                    className="text-sm font-bold text-[#f5f000] underline hover:text-white"
                >
                    {match.competition_name} · {match.season_start.slice(0, 4)}/
                    {match.season_end.slice(2, 4)}
                </Link>
                <h1 className="mt-5 text-3xl font-black text-[#3355dd]">
                    <ClubLink
                        clubId={match.hometeam_id}
                        name={match.home_team}
                        seasonId={match.season_id}
                    />
                    {' v '}
                    <ClubLink
                        clubId={match.awayteam_id}
                        name={match.away_team}
                        seasonId={match.season_id}
                    />
                </h1>
                <p className="mt-4 text-4xl font-black tabular-nums">
                    {completed
                        ? `${match.home_team_goals ?? '–'} – ${match.away_team_goals ?? '–'}`
                        : 'v'}
                </p>
                <p className="mt-3 font-bold capitalize">
                    {match.status.replaceAll('_', ' ')}
                    {match.leg_number !== null && ` · Leg ${match.leg_number}`}
                </p>
            </header>
            <main className="flex-1 bg-[#0c0c14] p-6 text-white">
                <dl className="grid gap-4 border border-[#3355dd]/50 bg-[#101358] p-5 text-sm sm:grid-cols-3">
                    <div>
                        <dt className="font-bold text-[#f5f000]">Kickoff</dt>
                        <dd className="mt-1">
                            {matchDate(match.match_start)}
                            {match.match_start &&
                                ` · ${match.match_start.slice(11, 16)}`}
                        </dd>
                    </div>
                    <div>
                        <dt className="font-bold text-[#f5f000]">Venue</dt>
                        <dd className="mt-1">
                            {match.stadium_name ?? 'Venue to be confirmed'}
                        </dd>
                    </div>
                    <div>
                        <dt className="font-bold text-[#f5f000]">Attendance</dt>
                        <dd className="mt-1">
                            {completed && match.attendance !== null
                                ? new Intl.NumberFormat('en-GB').format(
                                      match.attendance,
                                  )
                                : '—'}
                        </dd>
                    </div>
                </dl>
                <section
                    className="mt-6 overflow-hidden border border-[#3355dd]/50"
                    aria-label="Match events"
                >
                    <h2 className="bg-[#101358] px-5 py-3 font-bold text-[#f5f000]">
                        Match events
                    </h2>
                    {events.length === 0 ? (
                        <p className="p-6 text-center text-white/70">
                            {completed ||
                            ['cancelled', 'abandoned'].includes(match.status)
                                ? 'No match events were recorded.'
                                : 'Match events will appear after the match is played.'}
                        </p>
                    ) : (
                        <ol className="divide-y divide-white/10">
                            {events.map((event) => (
                                <li
                                    key={event.id}
                                    className="flex items-center gap-5 px-5 py-4"
                                >
                                    <span className="w-12 font-bold text-[#f5f000] tabular-nums">
                                        {event.minute}′
                                    </span>
                                    <span className="flex-1">
                                        {event.club_id === match.hometeam_id
                                            ? match.home_team
                                            : event.club_id ===
                                                match.awayteam_id
                                              ? match.away_team
                                              : 'Match event'}
                                    </span>
                                    <span className="capitalize">
                                        {event.type.replaceAll('_', ' ')}
                                    </span>
                                </li>
                            ))}
                        </ol>
                    )}
                </section>
            </main>
        </GameLayout>
    );
}
