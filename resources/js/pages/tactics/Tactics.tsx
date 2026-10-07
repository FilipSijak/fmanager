import { Head, Link } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import GameLayout from '@/layouts/GameLayout';
import { squad } from '@/routes';
import { useSquad } from '../squad/useSquad';
import PlayerListRow from './components/PlayerListRow';
import TeamInstructionsModal from './components/TeamInstructionsModal';
import { useTactics } from './useTactics';
import {
    lineupPlayersForSlots,
    pitchPlayersForFormation,
    sortByLineupPosition,
    STARTER_SLOT_IDS,
    SUBSTITUTE_SLOT_IDS,
} from './utils';

const pitchTabs = ['Overview', 'With Ball', 'Without Ball'] as const;

const toolbarControls = ['View'] as const;
const rightToolbarControls = ['Last Match', 'Edit'] as const;

export default function Tactics() {
    const { clubName, players, loadError: squadLoadError } = useSquad();
    const {
        tactics,
        assignments,
        swapLineupSlots,
        lineupError,
        loadError: tacticsLoadError,
        saveTactics,
        isSaving,
        saveError,
        clearSaveError,
    } = useTactics();
    const [isInstructionsOpen, setIsInstructionsOpen] = useState(false);
    const loadError = tacticsLoadError ?? squadLoadError;

    if (loadError) {
        return (
            <GameLayout active="Nations & Clubs">
                <Head title="Tactics" />
                <main className="flex min-h-screen flex-1 items-center justify-center bg-[#0c0c14]">
                    <p className="text-sm font-bold text-red-400">
                        {loadError}
                    </p>
                </main>
            </GameLayout>
        );
    }

    if (tactics === null || players === null) {
        return (
            <GameLayout active="Nations & Clubs">
                <Head title="Tactics" />
                <main className="flex min-h-screen flex-1 items-center justify-center bg-[#0c0c14]">
                    <p className="text-sm font-bold text-white">
                        Loading tactics...
                    </p>
                </main>
            </GameLayout>
        );
    }

    const selectedFormation = tactics.tactic.formation;
    const startingEleven = sortByLineupPosition(
        lineupPlayersForSlots(
            STARTER_SLOT_IDS,
            assignments,
            players,
            selectedFormation,
            1,
        ),
        selectedFormation,
    );
    const substitutes = lineupPlayersForSlots(
        SUBSTITUTE_SLOT_IDS,
        assignments,
        players,
        selectedFormation,
        STARTER_SLOT_IDS.length + 1,
    );
    const starterIds = new Set(
        startingEleven.map((lineupPlayer) => lineupPlayer.player.id),
    );
    const rolePlayers = [
        ...startingEleven.map((lineupPlayer) => lineupPlayer.player),
        ...players.filter((player) => !starterIds.has(player.id)),
    ];
    const pitchPlayers = pitchPlayersForFormation(
        selectedFormation,
        assignments,
        players,
    );
    const formationName = selectedFormation.name ?? selectedFormation.code;

    return (
        <GameLayout active="Nations & Clubs">
            <Head title={`${clubName ?? 'Club'} - Tactics`} />

            <header className="flex h-[92px] items-center justify-center border-b border-black bg-black px-6">
                <h1 className="text-3xl font-bold text-red-600">
                    {clubName ?? 'Club'} Tactics
                </h1>
            </header>

            <div className="flex flex-1 flex-col overflow-hidden bg-[#0c0c14]">
                <div className="flex flex-wrap items-center gap-2 px-5 pt-4">
                    <button
                        type="button"
                        onClick={() => setIsInstructionsOpen(true)}
                        className="rounded border border-slate-400 bg-[#c9c9cc] px-4 py-1.5 text-sm font-semibold text-slate-800 hover:bg-slate-300"
                    >
                        Team Instructions
                    </button>
                    {toolbarControls.map((label) => (
                        <button
                            key={label}
                            type="button"
                            className="flex items-center gap-2 rounded border border-slate-400 bg-[#c9c9cc] px-4 py-1.5 text-sm font-semibold text-slate-800 hover:bg-slate-300"
                        >
                            {label}
                            <ChevronDown size={14} />
                        </button>
                    ))}
                    <div className="flex-1" />
                    {rightToolbarControls.map((label) => (
                        <button
                            key={label}
                            type="button"
                            className="flex items-center gap-2 rounded border border-slate-400 bg-[#8a8a8d] px-4 py-1.5 text-sm font-semibold text-slate-100 hover:bg-slate-500"
                        >
                            {label}
                            <ChevronDown size={14} />
                        </button>
                    ))}
                </div>

                <div className="flex items-center justify-between px-5 py-3">
                    <h2 className="text-lg font-bold text-[#f5f000]">
                        {formationName}
                    </h2>
                    <div className="flex overflow-hidden rounded border border-white/10">
                        {pitchTabs.map((tab) => (
                            <button
                                key={tab}
                                type="button"
                                className={`border-r border-white/10 px-4 py-2 text-sm font-bold last:border-r-0 ${
                                    tab === 'Overview'
                                        ? 'bg-[#0031a5] text-white'
                                        : 'bg-[#c9c9cc] text-slate-800 hover:bg-slate-300'
                                }`}
                            >
                                {tab}
                            </button>
                        ))}
                    </div>
                </div>

                {lineupError && (
                    <p className="px-5 pb-2 text-center text-xs font-bold text-red-400">
                        {lineupError}
                    </p>
                )}

                <div className="min-h-0 flex-1 overflow-y-auto px-5 pb-4">
                    <div className="grid grid-cols-[minmax(0,320px)_1fr] gap-4">
                        <div className="overflow-hidden rounded border border-white/10">
                            {startingEleven.length === 0 &&
                            substitutes.length === 0 ? (
                                <p className="p-4 text-center text-sm text-white/60">
                                    No lineup selected yet.{' '}
                                    <Link
                                        href={squad.url()}
                                        className="font-bold text-[#f5f000] hover:underline"
                                    >
                                        Pick your squad
                                    </Link>
                                </p>
                            ) : (
                                <>
                                    <div className="divide-y divide-white/10">
                                        {startingEleven.map((lineupPlayer) => (
                                            <PlayerListRow
                                                key={lineupPlayer.player.id}
                                                lineupPlayer={lineupPlayer}
                                                isStarter
                                                onSwap={(fromSlotId) =>
                                                    swapLineupSlots(
                                                        fromSlotId,
                                                        lineupPlayer.slotId,
                                                    )
                                                }
                                            />
                                        ))}
                                    </div>
                                    <div className="h-2 bg-black" />
                                    <div className="divide-y divide-white/10">
                                        {substitutes.map((lineupPlayer) => (
                                            <PlayerListRow
                                                key={lineupPlayer.player.id}
                                                lineupPlayer={lineupPlayer}
                                                isStarter={false}
                                                onSwap={(fromSlotId) =>
                                                    swapLineupSlots(
                                                        fromSlotId,
                                                        lineupPlayer.slotId,
                                                    )
                                                }
                                            />
                                        ))}
                                    </div>
                                </>
                            )}
                        </div>

                        <div className="relative min-h-[520px] overflow-hidden rounded border border-white/10 bg-gradient-to-b from-emerald-700 via-emerald-800 to-emerald-700">
                            <div
                                aria-hidden
                                className="pointer-events-none absolute inset-0 opacity-30"
                            >
                                <div className="absolute inset-4 border-2 border-white" />
                                <div className="absolute inset-x-0 top-1/2 h-px bg-white" />
                                <div className="absolute top-1/2 left-1/2 size-24 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white" />
                            </div>

                            {pitchPlayers.map((player) => (
                                <div
                                    key={player.key}
                                    className="absolute flex -translate-x-1/2 -translate-y-1/2 flex-col items-center transition-all duration-300 ease-out"
                                    style={{
                                        left: `${player.x}%`,
                                        top: `${player.y}%`,
                                    }}
                                >
                                    {player.hasArrow && (
                                        <span className="absolute -top-7 h-6 w-px border-l border-dashed border-white/80" />
                                    )}
                                    <span className="flex size-8 items-center justify-center rounded-full border border-white/40 bg-red-900 text-sm font-bold text-white">
                                        {player.number}
                                    </span>
                                    <span className="mt-0.5 whitespace-nowrap text-xs font-bold text-[#f5f000]">
                                        {player.label}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>

            <div className="flex bg-[#86888a]">
                <button
                    type="button"
                    className="flex-1 border-r border-slate-400 py-4 text-lg font-bold text-slate-900 hover:bg-slate-400/40"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    className="flex-1 py-4 text-lg font-bold text-slate-900 hover:bg-slate-400/40"
                >
                    Ok
                </button>
            </div>
            {isInstructionsOpen && (
                <TeamInstructionsModal
                    tactics={tactics}
                    players={rolePlayers}
                    isSaving={isSaving}
                    saveError={saveError}
                    onSave={saveTactics}
                    onClose={() => {
                        clearSaveError();
                        setIsInstructionsOpen(false);
                    }}
                />
            )}
        </GameLayout>
    );
}
