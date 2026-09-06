import { Head } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import GameLayout from '@/layouts/GameLayout';

type Player = {
    name: string;
    position: string;
    marker?: 'international';
    keyPlayer?: boolean;
};

const leftColumn: Player[] = [
    { name: 'Begovic, A', position: 'GK' },
    { name: 'Azpilicueta, C', position: 'D RLC' },
    { name: 'Alonso, M', position: 'D/DM L' },
    { name: 'Cahill, G', position: 'D C' },
    { name: 'Terry, J', position: 'D C' },
    { name: 'Kanté, N', position: 'DM C', keyPlayer: true },
    { name: 'Fàbregas, C', position: 'M C' },
    { name: 'Moses, V', position: 'AM RL' },
    { name: 'Angban, V', position: 'AM C' },
    { name: 'Batshuayi, M', position: 'S C' },
];

const rightColumn: Player[] = [
    { name: 'Courtois, T', position: 'GK' },
    { name: 'Zouma, K', position: 'D/DM RC' },
    { name: 'Baba, A', position: 'D L' },
    { name: 'David Luiz', position: 'D/DM C' },
    { name: 'Chalobah, N', position: 'DM C' },
    { name: 'Matic, N', position: 'DM C' },
    { name: 'Hazard, E', position: 'AM/F RLC', keyPlayer: true },
    { name: 'Willian', position: 'AM/F RLC', marker: 'international' },
    { name: 'Pedro', position: 'F RLC' },
    { name: 'Diego Costa', position: 'S C', keyPlayer: true },
];

const positionChips = [
    'GK',
    'DL',
    'DR',
    'DC-1',
    'DC-2',
    'ML',
    'MR',
    'MC-1',
    'MC-2',
    'FC-1',
    'FC-2',
    'SB1',
    'SB2',
    'SB3',
    'SB4',
    'SB5',
    'SB6',
    'SB7',
].map((chip) => ({ id: chip, label: chip.replace(/-\d$/, '') }));

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

function PlayerRow({ player }: { player: Player }) {
    return (
        <div className="flex items-center gap-2 py-1.5">
            <span className="size-5 shrink-0 rounded-sm bg-[#0000a5]" />
            {player.marker === 'international' && (
                <span className="rounded-sm bg-[#0000a5] px-1.5 py-0.5 text-[10px] font-bold text-white">
                    Int
                </span>
            )}
            <span className="flex-1 truncate text-[15px] font-bold text-white">
                {player.name}
                {player.keyPlayer ? '*' : ''}
            </span>
            <span className="shrink-0 text-sm font-bold text-[#f5f000]">
                {player.position}
            </span>
        </div>
    );
}

export default function Squad() {
    return (
        <GameLayout active="Nations & Clubs">
            <Head title="Chelsea - Squad" />

            <header className="flex h-[92px] items-center justify-center border-b border-black bg-[#0031a5] px-6">
                <h1 className="text-3xl font-bold text-white">Chelsea</h1>
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
                </div>

                <h2 className="mt-3 text-center text-lg font-bold text-[#f5f000]">
                    Position(s)
                </h2>

                <div className="flex flex-wrap justify-center gap-1 px-5 py-2">
                    {positionChips.map((chip) => (
                        <span
                            key={chip.id}
                            className="rounded-sm bg-[#123a10] px-2 py-1 text-xs font-bold text-emerald-300"
                        >
                            {chip.label}
                        </span>
                    ))}
                </div>

                <div className="min-h-0 flex-1 overflow-y-auto px-6 pt-2 pb-4">
                    <div className="grid grid-cols-2 gap-x-10">
                        <div className="divide-y divide-white/10">
                            {leftColumn.map((player) => (
                                <PlayerRow key={player.name} player={player} />
                            ))}
                        </div>
                        <div className="divide-y divide-white/10">
                            {rightColumn.map((player) => (
                                <PlayerRow key={player.name} player={player} />
                            ))}
                        </div>
                    </div>
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
