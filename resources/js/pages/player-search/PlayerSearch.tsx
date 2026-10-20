import { Head, Link, useRemember } from '@inertiajs/react';
import axios from 'axios';
import { type FormEvent, useEffect, useRef, useState } from 'react';
import searchPlayers from '@/actions/App/Http/Controllers/PlayerSearchController';
import api from '@/api';
import ClubLink from '@/components/game/ClubLink';
import GameLayout from '@/layouts/GameLayout';
import { playerProfile } from '@/routes';

type SearchPlayer = {
    id: number;
    first_name: string;
    last_name: string;
    position: string;
    country_code: string;
    club: { id: number; name: string } | null;
};

type SearchState = {
    query: string;
    searchedQuery: string | null;
    players: SearchPlayer[];
};

export default function PlayerSearch() {
    const [search, setSearch] = useRemember<SearchState>(
        { query: '', searchedQuery: null, players: [] },
        'PlayerSearch',
    );
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const request = useRef<AbortController | null>(null);

    useEffect(() => () => request.current?.abort(), []);

    async function submitSearch(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (loading) return;
        const query = search.query.trim();
        if (query.length < 2) {
            setError('Enter at least two characters to search.');
            return;
        }

        const controller = new AbortController();
        request.current?.abort();
        request.current = controller;
        setError(null);
        setLoading(true);
        setSearch((previous) => ({
            ...previous,
            query,
            searchedQuery: null,
            players: [],
        }));

        try {
            const response = await api.get<{ data: SearchPlayer[] }>(
                searchPlayers.url({ query: { q: query } }),
                { signal: controller.signal },
            );
            if (!controller.signal.aborted) {
                setSearch((previous) => ({
                    ...previous,
                    players: response.data.data,
                    searchedQuery: query,
                }));
            }
        } catch (failure) {
            if (!controller.signal.aborted) {
                const details = axios.isAxiosError<{
                    error?: string;
                    errors?: { q?: string[] };
                }>(failure)
                    ? failure.response?.data
                    : undefined;
                setError(
                    details?.errors?.q?.[0] ??
                        details?.error ??
                        'Unable to search for players. Please try again.',
                );
            }
        } finally {
            if (!controller.signal.aborted) setLoading(false);
        }
    }

    return (
        <GameLayout active="Find">
            <Head title="Find Players" />
            <header className="border-b border-[#3355dd]/50 bg-black px-6 pt-14 pb-6 text-center">
                <h1 className="text-3xl font-black text-[#3355dd]">
                    Find Players
                </h1>
                <p className="mt-2 text-sm text-white/60">
                    Search players by first name or surname.
                </p>
            </header>
            <main className="flex-1 bg-[#0c0c14] text-white">
                <form
                    onSubmit={(event) => void submitSearch(event)}
                    className="flex flex-wrap items-end gap-3 border-b border-[#3355dd]/40 bg-[#101358] p-5"
                >
                    <div className="min-w-60 flex-1">
                        <label
                            htmlFor="player-name"
                            className="mb-2 block text-sm font-bold text-[#f5f000]"
                        >
                            Player name
                        </label>
                        <input
                            id="player-name"
                            name="q"
                            type="search"
                            value={search.query}
                            onChange={(event) =>
                                setSearch((previous) => ({
                                    ...previous,
                                    query: event.target.value,
                                }))
                            }
                            required
                            minLength={2}
                            maxLength={100}
                            disabled={loading}
                            aria-describedby="search-help"
                            className="w-full border border-[#3355dd] bg-black px-3 py-2 text-white focus:outline-2 focus:outline-[#f5f000] disabled:opacity-60"
                        />
                        <p
                            id="search-help"
                            className="mt-2 text-xs text-white/60"
                        >
                            Enter at least two characters. Up to 20 matches are
                            shown.
                        </p>
                    </div>
                    <button
                        type="submit"
                        disabled={loading}
                        className="border border-[#f5f000] px-5 py-2 font-bold text-[#f5f000] hover:bg-[#f5f000]/10 disabled:opacity-60"
                    >
                        {loading ? 'Searching…' : 'Find players'}
                    </button>
                </form>

                <section aria-label="Player search results" aria-busy={loading}>
                    {error && (
                        <p
                            role="alert"
                            className="m-5 border border-red-500/50 bg-red-950 px-4 py-3 text-sm text-red-100"
                        >
                            {error}
                        </p>
                    )}
                    {loading && (
                        <p
                            role="status"
                            className="p-8 text-center text-white/70"
                        >
                            Searching players…
                        </p>
                    )}
                    {!loading && !error && search.searchedQuery === null && (
                        <p className="p-8 text-center text-white/70">
                            Enter a player name to start searching.
                        </p>
                    )}
                    {!loading && !error && search.searchedQuery !== null && (
                        <>
                            <p
                                role="status"
                                className="px-5 py-4 text-sm text-white/70"
                            >
                                {search.players.length === 0
                                    ? `No players found for “${search.searchedQuery}”. Try another name.`
                                    : `Showing ${search.players.length} ${search.players.length === 1 ? 'match' : 'matches'} for “${search.searchedQuery}”.`}
                                {search.players.length === 20 &&
                                    ' Refine the name to narrow your search.'}
                            </p>
                            {search.players.length > 0 && (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-sm">
                                        <caption className="sr-only">
                                            Players matching{' '}
                                            {search.searchedQuery}
                                        </caption>
                                        <thead className="bg-[#101358] text-white">
                                            <tr>
                                                {[
                                                    'Player',
                                                    'Position',
                                                    'Club',
                                                    'Nation',
                                                ].map((heading) => (
                                                    <th
                                                        key={heading}
                                                        scope="col"
                                                        className="px-5 py-3"
                                                    >
                                                        {heading}
                                                    </th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {search.players.map((player) => (
                                                <tr
                                                    key={player.id}
                                                    className="bg-white text-slate-900 even:bg-[#d9d9d9]"
                                                >
                                                    <th
                                                        scope="row"
                                                        className="px-5 py-3"
                                                    >
                                                        <Link
                                                            href={playerProfile.url(
                                                                player.id,
                                                            )}
                                                            className="font-bold underline underline-offset-4 hover:text-[#3355dd]"
                                                        >
                                                            {player.first_name}{' '}
                                                            {player.last_name}
                                                        </Link>
                                                    </th>
                                                    <td className="px-5 py-3">
                                                        {player.position}
                                                    </td>
                                                    <td className="px-5 py-3">
                                                        {player.club ? (
                                                            <ClubLink
                                                                clubId={
                                                                    player.club
                                                                        .id
                                                                }
                                                                name={
                                                                    player.club
                                                                        .name
                                                                }
                                                            />
                                                        ) : (
                                                            'Free agent'
                                                        )}
                                                    </td>
                                                    <td className="px-5 py-3">
                                                        {player.country_code}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </>
                    )}
                </section>
            </main>
        </GameLayout>
    );
}
