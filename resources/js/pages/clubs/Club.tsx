import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import GameLayout from '@/layouts/GameLayout';
import { playerProfile } from '@/routes';
import { show as showClub } from '@/routes/clubs';
import { show as showCompetition } from '@/routes/competitions';
import MatchList from '../competitions/MatchList';
import type { CompetitionMatch } from '../competitions/types';

type ClubProps = {
    club: {
        id: number;
        name: string;
        country_code: string;
        rank: number;
        rank_academy: number;
        rank_training: number;
        stadium: {
            name: string;
            active_capacity: number | null;
            capacity: number;
        } | null;
    };
    season: { id: number; start_date: string; end_date: string };
    seasons: { id: number; start_date: string; end_date: string }[];
    competitions: {
        id: number;
        name: string;
        type: string;
        group_id: number | null;
        played: number;
        points: number;
    }[];
    matches: CompetitionMatch[];
    squad: {
        id: number;
        name: string;
        position: string;
        country_code: string;
    }[];
};

export default function Club(props: ClubProps) {
    return (
        <ClubScreen key={`${props.club.id}-${props.season.id}`} {...props} />
    );
}

function ClubScreen({
    club,
    season,
    seasons,
    competitions,
    matches,
    squad,
}: ClubProps) {
    const [tab, setTab] = useState('Competitions');
    const results = matches
        .filter((match) =>
            ['completed', 'cancelled', 'abandoned'].includes(match.status),
        )
        .reverse();
    const fixtures = matches.filter((match) =>
        ['scheduled', 'postponed', 'in_progress'].includes(match.status),
    );
    return (
        <GameLayout active="Competitions">
            <Head title={club.name} />
            <header className="border-b border-[#3355dd]/50 bg-black px-6 pt-14 pb-6 text-center text-white">
                <p className="text-xs font-bold tracking-widest text-white/60 uppercase">
                    Club overview · {club.country_code}
                </p>
                <h1 className="mt-2 text-3xl font-black text-[#3355dd]">
                    {club.name}
                </h1>
                <div className="mx-auto mt-4 flex max-w-sm items-center gap-3">
                    <label
                        htmlFor="club-season"
                        className="text-sm font-bold text-[#f5f000]"
                    >
                        Season
                    </label>
                    <select
                        id="club-season"
                        value={season.id}
                        onChange={(event) =>
                            router.visit(
                                showClub.url(club.id, {
                                    query: {
                                        season_id: Number(event.target.value),
                                    },
                                }),
                            )
                        }
                        className="min-w-0 flex-1 border border-[#3355dd] bg-[#101358] px-3 py-2 text-sm text-white"
                    >
                        {seasons.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.start_date.slice(0, 4)}/
                                {item.end_date.slice(2, 4)}
                            </option>
                        ))}
                    </select>
                </div>
            </header>
            <main className="flex-1 bg-[#0c0c14] text-white">
                <section
                    aria-label="Current club details"
                    className="border-b border-[#3355dd]/40 p-5"
                >
                    <h2 className="mb-3 text-sm font-bold text-[#f5f000]">
                        Current club details
                    </h2>
                    <dl className="grid gap-4 text-sm sm:grid-cols-4">
                        <div>
                            <dt className="text-white/60">Stadium</dt>
                            <dd className="mt-1 font-bold">
                                {club.stadium?.name ?? 'No stadium'}
                                {club.stadium && (
                                    <span className="block text-xs font-normal text-white/60">
                                        Capacity:{' '}
                                        {new Intl.NumberFormat('en-GB').format(
                                            club.stadium.active_capacity ??
                                                club.stadium.capacity,
                                        )}
                                    </span>
                                )}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-white/60">Club rating</dt>
                            <dd className="mt-1 font-bold">{club.rank}</dd>
                        </div>
                        <div>
                            <dt className="text-white/60">Training rating</dt>
                            <dd className="mt-1 font-bold">
                                {club.rank_training}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-white/60">Academy rating</dt>
                            <dd className="mt-1 font-bold">
                                {club.rank_academy}
                            </dd>
                        </div>
                    </dl>
                </section>
                <nav aria-label="Club views" className="flex bg-[#200061]">
                    {[
                        'Competitions',
                        'Results',
                        'Fixtures',
                        'Current squad',
                    ].map((item) => (
                        <button
                            key={item}
                            type="button"
                            aria-pressed={tab === item}
                            onClick={() => setTab(item)}
                            className={`flex-1 border-r border-white/10 px-4 py-4 text-sm font-bold ${tab === item ? 'bg-[#1a0050] text-[#f5f000] ring-2 ring-inset ring-[#f5f000]' : 'hover:bg-white/5'}`}
                        >
                            {item}
                        </button>
                    ))}
                </nav>
                {tab === 'Competitions' &&
                    (competitions.length === 0 ? (
                        <p className="p-8 text-center text-white/70">
                            No competition entries for this season.
                        </p>
                    ) : (
                        <ul className="divide-y divide-white/10">
                            {competitions.map((competition) => (
                                <li
                                    key={competition.id}
                                    className="flex items-center justify-between gap-5 px-5 py-4"
                                >
                                    <Link
                                        href={showCompetition.url(
                                            competition.id,
                                            { query: { season_id: season.id } },
                                        )}
                                        className="font-bold text-[#f5f000] underline hover:text-white"
                                    >
                                        {competition.name}
                                    </Link>
                                    <span className="text-sm text-white/70">
                                        {competition.type === 'league' ||
                                        competition.group_id !== null
                                            ? `${competition.played} played · ${competition.points} points`
                                            : 'Cup'}
                                        {competition.group_id !== null &&
                                            ` · Group ${competition.group_id}`}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    ))}
                {tab === 'Results' && (
                    <MatchList
                        matches={results}
                        clubId={club.id}
                        showCompetition
                        emptyMessage="No results for this season."
                    />
                )}
                {tab === 'Fixtures' && (
                    <MatchList
                        matches={fixtures}
                        clubId={club.id}
                        showCompetition
                        emptyMessage="No upcoming fixtures for this season."
                    />
                )}
                {tab === 'Current squad' &&
                    (squad.length === 0 ? (
                        <p className="p-8 text-center text-white/70">
                            There are no active players in this squad.
                        </p>
                    ) : (
                        <div className="p-5">
                            <p className="mb-4 text-xs text-white/60">
                                Players currently at the club, including when
                                browsing an archived season.
                            </p>
                            <ul className="divide-y divide-white/10">
                                {squad.map((player) => (
                                    <li
                                        key={player.id}
                                        className="flex items-center gap-5 py-3 text-sm"
                                    >
                                        <span className="w-12 font-bold text-[#f5f000]">
                                            {player.position}
                                        </span>
                                        <Link
                                            href={playerProfile.url(player.id)}
                                            className="flex-1 font-bold hover:underline"
                                        >
                                            {player.name}
                                        </Link>
                                        <span className="text-white/60">
                                            {player.country_code}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
            </main>
        </GameLayout>
    );
}
