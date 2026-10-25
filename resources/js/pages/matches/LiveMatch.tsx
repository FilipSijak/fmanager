import { Head } from '@inertiajs/react';
import { useState } from 'react';
import GameLayout from '@/layouts/GameLayout';
import type { Formation } from '../tactics/types';
import MatchStatsTab from './live/components/MatchStatsTab';
import TacticsTab from './live/components/TacticsTab';
import TeamSheet from './live/components/TeamSheet';
import type { LiveMatchData } from './live/types';
import { SPEEDS, useLiveMatch } from './live/useLiveMatch';

type Tab = 'stats' | 'teams' | 'tactics';

const TABS: { id: Tab; label: string }[] = [
    { id: 'stats', label: 'Match stats' },
    { id: 'teams', label: 'Teams & substitutions' },
    { id: 'tactics', label: 'Tactics' },
];

export default function LiveMatch({
    match: initialMatch,
    formations,
    mentalities,
}: {
    match: LiveMatchData;
    formations: Formation[];
    mentalities: string[];
}) {
    const {
        match,
        playing,
        speed,
        setSpeed,
        togglePlaying,
        substitute,
        changeTactics,
        continueAfterMatch,
        error,
        busy,
    } = useLiveMatch(initialMatch);
    const [tab, setTab] = useState<Tab>('stats');
    const [playerOffId, setPlayerOffId] = useState<number | null>(null);
    const [playerOnId, setPlayerOnId] = useState<number | null>(null);

    const userTeam = match.user_side === 'home' ? match.home : match.away;
    const substitutionsLeft = match.max_substitutions - userTeam.substitutions_used;
    const canSubstitute = !playing && !match.finished && substitutionsLeft > 0;
    const playerName = (playerId: number | null) =>
        [...userTeam.on_pitch, ...userTeam.bench].find(
            (player) => player.player_id === playerId,
        )?.name;
    const heading = `${match.home.name} v ${match.away.name}`;

    async function makeSubstitution(offId: number, onId: number) {
        const made = await substitute(offId, onId);

        if (made) {
            setPlayerOffId(null);
            setPlayerOnId(null);
        }

        return made;
    }

    async function confirmSubstitution() {
        if (playerOffId !== null && playerOnId !== null) {
            await makeSubstitution(playerOffId, playerOnId);
        }
    }

    const selection = canSubstitute
        ? {
              playerOffId,
              playerOnId,
              onSelectOff: (playerId: number) =>
                  setPlayerOffId((current) => (current === playerId ? null : playerId)),
              onSelectOn: (playerId: number) =>
                  setPlayerOnId((current) => (current === playerId ? null : playerId)),
              onSubstitute: (offId: number, onId: number) => void makeSubstitution(offId, onId),
          }
        : null;

    return (
        <GameLayout>
            <Head title={heading} />
            <header className="border-b border-[#3355dd]/50 bg-black px-6 pt-14 pb-6 text-center text-white">
                <h1 className="grid grid-cols-[1fr_auto_1fr] items-center gap-6 text-3xl font-black text-[#3355dd]">
                    <span className="text-right">{match.home.name}</span>
                    <span className="text-5xl text-white tabular-nums">
                        {match.home.goals} – {match.away.goals}
                    </span>
                    <span className="text-left">{match.away.name}</span>
                </h1>
                <p
                    className="mt-3 text-2xl font-bold text-[#f5f000] tabular-nums"
                    aria-live="polite"
                >
                    {match.finished
                        ? 'Full time'
                        : match.minute === 0
                          ? 'Kick-off'
                          : `${match.minute}′`}
                </p>
                <div className="mt-5 flex flex-wrap items-center justify-center gap-3">
                    {match.finished ? (
                        <button
                            type="button"
                            onClick={continueAfterMatch}
                            disabled={busy}
                            className="border-2 border-[#f5f000] bg-[#f5f000] px-6 py-2 font-black text-black uppercase hover:bg-white disabled:opacity-50"
                        >
                            {busy ? 'Continuing…' : 'Continue'}
                        </button>
                    ) : (
                        <>
                            <button
                                type="button"
                                onClick={togglePlaying}
                                className="w-36 border-2 border-[#f5f000] px-6 py-2 font-black text-[#f5f000] uppercase hover:bg-[#f5f000] hover:text-black"
                            >
                                {playing ? 'Pause' : match.minute === 0 ? 'Kick off' : 'Play'}
                            </button>
                            <div
                                className="flex border border-[#3355dd]"
                                role="group"
                                aria-label="Match speed"
                            >
                                {SPEEDS.map((option, index) => (
                                    <button
                                        key={option.label}
                                        type="button"
                                        onClick={() => setSpeed(index)}
                                        aria-pressed={speed === index}
                                        className={`px-3 py-2 font-bold ${
                                            speed === index
                                                ? 'bg-[#3355dd] text-white'
                                                : 'text-[#c8ffb0] hover:bg-[#3355dd]/40'
                                        }`}
                                    >
                                        {option.label}
                                    </button>
                                ))}
                            </div>
                        </>
                    )}
                </div>
                {error && (
                    <p className="mt-4 font-bold text-red-400" role="alert">
                        {error}
                    </p>
                )}
            </header>
            <main className="flex-1 bg-[#0c0c14] p-6 text-white">
                <div className="mb-6 flex gap-2 border-b border-[#3355dd]/50" role="tablist">
                    {TABS.map((option) => (
                        <button
                            key={option.id}
                            type="button"
                            role="tab"
                            aria-selected={tab === option.id}
                            onClick={() => setTab(option.id)}
                            className={`px-5 py-2 font-bold ${
                                tab === option.id
                                    ? 'bg-[#3355dd] text-white'
                                    : 'text-[#c8ffb0] hover:bg-[#3355dd]/30'
                            }`}
                        >
                            {option.label}
                        </button>
                    ))}
                </div>

                {tab === 'stats' && <MatchStatsTab match={match} />}
                {tab === 'tactics' && (
                    <TacticsTab
                        match={match}
                        formations={formations}
                        mentalities={mentalities}
                        editable={!playing && !match.finished}
                        busy={busy}
                        onApply={changeTactics}
                        onSubstitute={(offId, onId) =>
                            substitutionsLeft > 0 ? makeSubstitution(offId, onId) : Promise.resolve(false)
                        }
                    />
                )}
                {tab === 'teams' && (
                    <div className="flex flex-col gap-6">
                        <section
                            className="flex flex-wrap items-center gap-4 border border-[#3355dd]/50 bg-[#101358] px-5 py-3"
                            aria-label="Substitutions"
                        >
                            <span className="font-bold text-[#f5f000]">
                                Substitutions {userTeam.substitutions_used}/
                                {match.max_substitutions}
                            </span>
                            {match.finished ? (
                                <span>The match has finished.</span>
                            ) : playing ? (
                                <span>Pause the match to make a substitution.</span>
                            ) : substitutionsLeft === 0 ? (
                                <span>All substitutions have been made.</span>
                            ) : (
                                <>
                                    <span>
                                        Off:{' '}
                                        <strong>
                                            {playerName(playerOffId) ?? 'pick a player on the pitch'}
                                        </strong>
                                    </span>
                                    <span className="text-sm text-white/70">Click or drag to pick.</span>
                                    <span>
                                        On:{' '}
                                        <strong>
                                            {playerName(playerOnId) ?? 'pick a substitute'}
                                        </strong>
                                    </span>
                                    <button
                                        type="button"
                                        onClick={confirmSubstitution}
                                        disabled={busy || playerOffId === null || playerOnId === null}
                                        className="ml-auto border-2 border-[#f5f000] px-4 py-1 font-bold text-[#f5f000] hover:bg-[#f5f000] hover:text-black disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-[#f5f000]"
                                    >
                                        Make substitution
                                    </button>
                                </>
                            )}
                        </section>
                        <div className="grid gap-6 2xl:grid-cols-2">
                            {(['home', 'away'] as const).map((side) => (
                                <TeamSheet
                                    key={side}
                                    side={match[side]}
                                    selection={side === match.user_side ? selection : null}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </main>
        </GameLayout>
    );
}
