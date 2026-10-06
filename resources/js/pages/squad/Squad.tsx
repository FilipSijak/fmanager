import { Head } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import GameLayout from '@/layouts/GameLayout';
import PlayerRow from './components/PlayerRow';
import { useLineupBoard } from './useLineupBoard';
import { useSquad } from './useSquad';
import { LINEUP_CHIP_DRAG_TYPE, POSITION_CHIPS } from './utils';

const tabs = [
    'Squad',
    'Transfers',
    'Next Match',
    'Fixtures',
    'Finances & Info',
] as const;

const bottomTabs = [
    'Tactics',
    'Training',
    'Last Match',
    'Premier League',
    'History',
] as const;

export default function Squad() {
    const { clubName, players, loadError } = useSquad();
    const {
        chipIdForPlayer,
        playerIdForChip,
        assignChipToPlayer,
        togglePlayerBox,
        saveLineup,
        isSaving,
        saveError,
    } = useLineupBoard();

    if (loadError) {
        return (
            <GameLayout active="Nations & Clubs">
                <Head title="Squad" />
                <main className="flex min-h-screen flex-1 items-center justify-center bg-[#0c0c14]">
                    <p className="text-sm font-bold text-red-400">
                        {loadError}
                    </p>
                </main>
            </GameLayout>
        );
    }

    if (players === null) {
        return (
            <GameLayout active="Nations & Clubs">
                <Head title="Squad" />
                <main className="flex min-h-screen flex-1 items-center justify-center bg-[#0c0c14]">
                    <p className="text-sm font-bold text-white">
                        Loading squad...
                    </p>
                </main>
            </GameLayout>
        );
    }

    const half = Math.ceil(players.length / 2);
    const leftColumn = players.slice(0, half);
    const rightColumn = players.slice(half);

    return (
        <GameLayout active="Nations & Clubs">
            <Head title={`${clubName ?? 'Squad'} - Squad`} />

            <header className="flex h-[92px] items-center justify-center border-b border-black bg-[#0031a5] px-6">
                <h1 className="text-3xl font-bold text-white">
                    {clubName ?? 'Squad'}
                </h1>
            </header>

            <div className="flex bg-[#200064]">
                {tabs.map((tab) => (
                    <button
                        key={tab}
                        type="button"
                        className={`flex-1 border-r border-white/10 py-4 text-sm font-bold last:border-r-0 ${
                            tab === 'Squad'
                                ? 'bg-[#1a0050] text-[#f5f000] ring-2 ring-inset ring-[#f5f000]'
                                : 'text-white hover:bg-white/5'
                        }`}
                    >
                        {tab}
                    </button>
                ))}
            </div>

            <div className="flex flex-1 flex-col overflow-hidden bg-[#0c0c14]">
                <div className="flex flex-wrap gap-2 px-5 pt-4">
                    {['View', 'Sort By'].map((label) => (
                        <button
                            key={label}
                            type="button"
                            className="flex items-center gap-2 rounded border border-slate-400 bg-[#c9c9cc] px-4 py-1.5 text-sm font-semibold text-slate-800 hover:bg-slate-300"
                        >
                            {label}
                            <ChevronDown size={14} />
                        </button>
                    ))}
                    <button
                        type="button"
                        disabled
                        className="ml-auto rounded border border-slate-500 bg-[#8a8a8d] px-4 py-1.5 text-sm font-semibold text-slate-300"
                    >
                        Clear Squad
                    </button>
                    <button
                        type="button"
                        className="flex items-center gap-2 rounded border border-slate-400 bg-[#c9c9cc] px-4 py-1.5 text-sm font-semibold text-slate-800 hover:bg-slate-300"
                    >
                        Filter
                        <ChevronDown size={14} />
                    </button>
                    <button
                        type="button"
                        disabled={isSaving}
                        onClick={() => saveLineup(players)}
                        className="rounded border border-emerald-600 bg-emerald-600 px-4 py-1.5 text-sm font-bold text-white hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {isSaving ? 'Saving...' : 'Save Squad'}
                    </button>
                </div>

                {saveError && (
                    <p className="px-5 pt-2 text-center text-xs font-bold text-red-400">
                        {saveError}
                    </p>
                )}

                <h2 className="mt-3 text-center text-lg font-bold text-[#f5f000]">
                    Position(s)
                </h2>

                <div className="flex flex-wrap justify-center gap-1 px-5 py-2">
                    {POSITION_CHIPS.map((chip) => {
                        const assignedPlayer = players.find(
                            (player) => player.id === playerIdForChip(chip.id),
                        );

                        return (
                            <button
                                key={chip.id}
                                type="button"
                                draggable
                                onDragStart={(event) =>
                                    event.dataTransfer.setData(
                                        LINEUP_CHIP_DRAG_TYPE,
                                        chip.id,
                                    )
                                }
                                title={
                                    assignedPlayer
                                        ? `${assignedPlayer.first_name} ${assignedPlayer.last_name} - drag onto another player to reassign`
                                        : 'Drag onto a player to assign this position'
                                }
                                className={`cursor-grab rounded-sm px-2 py-1 text-xs font-bold ${
                                    assignedPlayer
                                        ? 'bg-emerald-700 text-white'
                                        : 'bg-[#123a10] text-emerald-300'
                                }`}
                            >
                                {chip.label}
                            </button>
                        );
                    })}
                </div>

                <div className="min-h-0 flex-1 overflow-y-auto px-6 pt-2 pb-4">
                    {players.length === 0 ? (
                        <p className="pt-6 text-center text-sm text-white/60">
                            No players in the squad yet.
                        </p>
                    ) : (
                        <div className="grid grid-cols-2 gap-x-10">
                            <div className="divide-y divide-white/10">
                                {leftColumn.map((player) => (
                                    <PlayerRow
                                        key={player.id}
                                        player={player}
                                        assignedChipId={chipIdForPlayer(
                                            player.id,
                                        )}
                                        onBoxClick={() =>
                                            togglePlayerBox(player.id)
                                        }
                                        onDropChip={(chipId) =>
                                            assignChipToPlayer(
                                                chipId,
                                                player.id,
                                            )
                                        }
                                    />
                                ))}
                            </div>
                            <div className="divide-y divide-white/10">
                                {rightColumn.map((player) => (
                                    <PlayerRow
                                        key={player.id}
                                        player={player}
                                        assignedChipId={chipIdForPlayer(
                                            player.id,
                                        )}
                                        onBoxClick={() =>
                                            togglePlayerBox(player.id)
                                        }
                                        onDropChip={(chipId) =>
                                            assignChipToPlayer(
                                                chipId,
                                                player.id,
                                            )
                                        }
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            <div className="flex bg-[#200064] text-sm font-bold text-white">
                {bottomTabs.map((tab) => (
                    <button
                        key={tab}
                        type="button"
                        className="flex flex-1 items-center justify-center gap-1 border-r border-white/10 py-3 last:border-r-0 hover:bg-white/5"
                    >
                        {tab}
                        <ChevronDown size={12} className="-rotate-90" />
                    </button>
                ))}
            </div>
        </GameLayout>
    );
}
