import { Head } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import GameLayout from '@/layouts/GameLayout';

type ListPlayer = {
    number: number;
    name: string;
    rating: number;
    inLineup?: boolean;
};

const startingEleven: ListPlayer[] = [
    { number: 1, name: 'Christian Abbiati', rating: 7.43, inLineup: true },
    { number: 2, name: 'Cosmin Contra', rating: 7.58, inLineup: true },
    { number: 11, name: 'Serginho', rating: 7.84, inLineup: true },
    { number: 6, name: 'Paolo Maldini', rating: 7.57, inLineup: true },
    { number: 18, name: 'José Antonio Chamot', rating: 7.21, inLineup: true },
    { number: 20, name: 'Alessandro Costacurta', rating: 7.0, inLineup: true },
    { number: 7, name: 'Gennaro Ivan Gattuso', rating: 7.21, inLineup: true },
    { number: 8, name: 'Rui Costa', rating: 7.25, inLineup: true },
    { number: 10, name: 'Andriy Shevchenko', rating: 8.0, inLineup: true },
    { number: 9, name: 'Filippo Inzaghi', rating: 7.48, inLineup: true },
    { number: 17, name: 'Massimo Ambrosini', rating: 6.83, inLineup: true },
];

const substitutes: ListPlayer[] = [
    { number: 13, name: 'Sebastiano Rossi', rating: 7.0 },
    { number: 3, name: 'Kakhaber Kaladze', rating: 6.85 },
    { number: 25, name: 'Roque Júnior', rating: 7.47 },
    { number: 29, name: 'Kennedy Bakircioğlu', rating: 6.67 },
    { number: 23, name: 'Ümit Davala', rating: 6.79 },
    { number: 24, name: 'Kim Källström', rating: 6.75 },
    { number: 32, name: 'Maxim Tsigalko', rating: 7.32 },
];

type PitchPlayer = {
    number: number;
    label: string;
    x: number;
    y: number;
    hasArrow?: boolean;
};

type FormationName = '5-3-2 Attacking' | '4-4-2' | '4-3-3' | '3-5-2';

const formations: Record<FormationName, PitchPlayer[]> = {
    '5-3-2 Attacking': [
        { number: 1, label: 'Abbiati', x: 50, y: 90 },
        { number: 6, label: 'Maldini', x: 32, y: 74 },
        { number: 20, label: 'Costacurta', x: 50, y: 76 },
        { number: 18, label: 'Chamot', x: 68, y: 74 },
        { number: 11, label: 'Serginho', x: 10, y: 62, hasArrow: true },
        { number: 2, label: 'Contra', x: 90, y: 62, hasArrow: true },
        { number: 17, label: 'Ambrosini', x: 30, y: 48, hasArrow: true },
        { number: 7, label: 'Gattuso', x: 70, y: 48, hasArrow: true },
        { number: 8, label: 'Rui Costa', x: 50, y: 32, hasArrow: true },
        { number: 10, label: 'Shevchenko', x: 36, y: 14 },
        { number: 9, label: 'Inzaghi', x: 64, y: 14 },
    ],
    '4-4-2': [
        { number: 1, label: 'Abbiati', x: 50, y: 90 },
        { number: 11, label: 'Serginho', x: 15, y: 74 },
        { number: 6, label: 'Maldini', x: 38, y: 78 },
        { number: 20, label: 'Costacurta', x: 62, y: 78 },
        { number: 2, label: 'Contra', x: 85, y: 74 },
        { number: 17, label: 'Ambrosini', x: 15, y: 50, hasArrow: true },
        { number: 7, label: 'Gattuso', x: 38, y: 52 },
        { number: 8, label: 'Rui Costa', x: 62, y: 52 },
        { number: 18, label: 'Chamot', x: 85, y: 50, hasArrow: true },
        { number: 10, label: 'Shevchenko', x: 36, y: 20 },
        { number: 9, label: 'Inzaghi', x: 64, y: 20 },
    ],
    '4-3-3': [
        { number: 1, label: 'Abbiati', x: 50, y: 90 },
        { number: 11, label: 'Serginho', x: 15, y: 74 },
        { number: 6, label: 'Maldini', x: 38, y: 78 },
        { number: 20, label: 'Costacurta', x: 62, y: 78 },
        { number: 2, label: 'Contra', x: 85, y: 74 },
        { number: 17, label: 'Ambrosini', x: 30, y: 54, hasArrow: true },
        { number: 7, label: 'Gattuso', x: 50, y: 58 },
        { number: 18, label: 'Chamot', x: 70, y: 54, hasArrow: true },
        { number: 10, label: 'Shevchenko', x: 20, y: 22 },
        { number: 8, label: 'Rui Costa', x: 50, y: 18, hasArrow: true },
        { number: 9, label: 'Inzaghi', x: 80, y: 22 },
    ],
    '3-5-2': [
        { number: 1, label: 'Abbiati', x: 50, y: 90 },
        { number: 6, label: 'Maldini', x: 30, y: 76 },
        { number: 20, label: 'Costacurta', x: 50, y: 80 },
        { number: 18, label: 'Chamot', x: 70, y: 76 },
        { number: 11, label: 'Serginho', x: 8, y: 56, hasArrow: true },
        { number: 2, label: 'Contra', x: 92, y: 56, hasArrow: true },
        { number: 17, label: 'Ambrosini', x: 30, y: 44 },
        { number: 7, label: 'Gattuso', x: 50, y: 48 },
        { number: 8, label: 'Rui Costa', x: 70, y: 44, hasArrow: true },
        { number: 10, label: 'Shevchenko', x: 38, y: 16 },
        { number: 9, label: 'Inzaghi', x: 62, y: 16 },
    ],
};

const formationNames = Object.keys(formations) as FormationName[];

const pitchTabs = ['Overview', 'With Ball', 'Without Ball'] as const;

const toolbarControls = ['View'] as const;
const rightToolbarControls = ['Last Match', 'Edit'] as const;

export default function Tactics() {
    const [formation, setFormation] =
        useState<FormationName>('5-3-2 Attacking');
    const [tacticsMenuOpen, setTacticsMenuOpen] = useState(false);
    const tacticsMenuRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!tacticsMenuOpen) {
            return;
        }

        function handleClickOutside(event: MouseEvent) {
            if (
                tacticsMenuRef.current &&
                !tacticsMenuRef.current.contains(event.target as Node)
            ) {
                setTacticsMenuOpen(false);
            }
        }

        document.addEventListener('mousedown', handleClickOutside);
        return () =>
            document.removeEventListener('mousedown', handleClickOutside);
    }, [tacticsMenuOpen]);

    const pitchPlayers = formations[formation];

    return (
        <GameLayout active="Nations & Clubs">
            <Head title="AC Milan - Tactics" />

            <header className="flex h-[92px] items-center justify-center border-b border-black bg-black px-6">
                <h1 className="text-3xl font-bold text-red-600">
                    AC Milan Tactics
                </h1>
            </header>

            <div className="flex flex-1 flex-col overflow-hidden bg-[#0c0c14]">
                <div className="flex flex-wrap items-center gap-2 px-5 pt-4">
                    <div className="relative" ref={tacticsMenuRef}>
                        <button
                            type="button"
                            onClick={() => setTacticsMenuOpen((open) => !open)}
                            className="flex items-center gap-2 rounded border border-slate-400 bg-[#c9c9cc] px-4 py-1.5 text-sm font-semibold text-slate-800 hover:bg-slate-300"
                        >
                            Tactics
                            <ChevronDown size={14} />
                        </button>
                        {tacticsMenuOpen && (
                            <div className="absolute top-full left-0 z-10 mt-1 w-48 overflow-hidden rounded border border-slate-400 bg-[#c9c9cc] shadow-lg">
                                {formationNames.map((name) => (
                                    <button
                                        key={name}
                                        type="button"
                                        onClick={() => {
                                            setFormation(name);
                                            setTacticsMenuOpen(false);
                                        }}
                                        className={`block w-full px-4 py-2 text-left text-sm font-semibold hover:bg-slate-300 ${
                                            name === formation
                                                ? 'bg-[#0031a5] text-white hover:bg-[#00268a]'
                                                : 'text-slate-800'
                                        }`}
                                    >
                                        {name}
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>
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
                        {formation}*
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

                <div className="min-h-0 flex-1 overflow-y-auto px-5 pb-4">
                    <div className="grid grid-cols-[minmax(0,320px)_1fr] gap-4">
                        <div className="overflow-hidden rounded border border-white/10">
                            <div className="divide-y divide-white/10">
                                {startingEleven.map((player) => (
                                    <PlayerListRow
                                        key={player.number}
                                        player={player}
                                    />
                                ))}
                            </div>
                            <div className="h-2 bg-black" />
                            <div className="divide-y divide-white/10">
                                {substitutes.map((player) => (
                                    <PlayerListRow
                                        key={player.number}
                                        player={player}
                                    />
                                ))}
                            </div>
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
                                    key={player.number}
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
        </GameLayout>
    );
}

function PlayerListRow({ player }: { player: ListPlayer }) {
    return (
        <div className="flex items-center gap-2 bg-[#0c0c14] px-2 py-1.5">
            <span className="w-6 shrink-0 text-center text-sm font-bold text-[#f5f000]">
                {player.number}
            </span>
            <span
                className={`flex-1 truncate text-sm font-semibold ${player.inLineup ? 'text-white' : 'text-slate-400'}`}
            >
                {player.name}
            </span>
            <span className="w-10 shrink-0 text-right text-sm font-bold text-[#f5f000]">
                {player.rating.toFixed(2)}
            </span>
        </div>
    );
}
