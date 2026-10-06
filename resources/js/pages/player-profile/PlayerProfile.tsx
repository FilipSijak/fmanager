import { Head } from '@inertiajs/react';
import { ChevronDown, ChevronLeft, ChevronRight } from 'lucide-react';
import GameLayout from '@/layouts/GameLayout';
import AttributeColumn from './components/AttributeColumn';
import { usePlayerProfile } from './usePlayerProfile';
import { ageFromDob, formatDob, POSITION_LABELS } from './utils';

type ExtraAttribute = {
    label: string;
    value: string;
};

const extraAttributes: ExtraAttribute[] = [
    { label: 'Preferred Foot', value: 'Right Only' },
    { label: 'Form', value: '8-7-7-7' },
    { label: 'Morale', value: 'Superb' },
    { label: 'Condition', value: '100%' },
];

type StatRow = {
    label: string;
    apps: string | number;
    gls: string | number;
    asts: string | number;
    mom: string | number;
    pass: string | number;
    tck: string | number;
    drb: string | number;
};

const statRows: StatRow[] = [
    {
        label: 'Non Competitive',
        apps: '-',
        gls: '-',
        asts: '-',
        mom: '-',
        pass: '-',
        tck: '-',
        drb: '-',
    },
    {
        label: 'League',
        apps: 0,
        gls: 0,
        asts: 0,
        mom: 0,
        pass: '-',
        tck: '-',
        drb: '-',
    },
    {
        label: 'Cup',
        apps: '-',
        gls: '-',
        asts: '-',
        mom: '-',
        pass: '-',
        tck: '-',
        drb: '-',
    },
    {
        label: 'Continental',
        apps: '-',
        gls: '-',
        asts: '-',
        mom: '-',
        pass: '-',
        tck: '-',
        drb: '-',
    },
    {
        label: 'International',
        apps: '-',
        gls: '-',
        asts: '-',
        mom: '-',
        pass: '-',
        tck: '-',
        drb: '-',
    },
    {
        label: 'Senior Club',
        apps: 0,
        gls: 0,
        asts: 0,
        mom: 0,
        pass: '-',
        tck: '-',
        drb: '-',
    },
];

const tabs = [
    'Profile',
    'Injuries & Bans',
    'Contract',
    'Transfer',
    'History',
] as const;

const statColumns = [
    'Apps',
    'Gls',
    'Asts',
    'MoM',
    'Pass',
    'Tck',
    'Drb',
    'Sh Tar',
    'Av R',
] as const;

export default function PlayerProfile({ playerId }: { playerId: number }) {
    const { player, instanceDate, loadError } = usePlayerProfile(playerId);

    if (loadError) {
        return (
            <GameLayout active="Nations & Clubs">
                <Head title="Player Profile" />
                <main className="flex min-h-screen flex-1 items-center justify-center bg-[#0c0c14]">
                    <p className="text-sm font-bold text-red-400">
                        {loadError}
                    </p>
                </main>
            </GameLayout>
        );
    }

    if (player === null) {
        return (
            <GameLayout active="Nations & Clubs">
                <Head title="Player Profile" />
                <main className="flex min-h-screen flex-1 items-center justify-center bg-[#0c0c14]">
                    <p className="text-sm font-bold text-white">
                        Loading player...
                    </p>
                </main>
            </GameLayout>
        );
    }

    const age = ageFromDob(player.dob, instanceDate);
    const positionLabel = POSITION_LABELS[player.position] ?? player.position;

    return (
        <GameLayout active="Nations & Clubs">
            <Head
                title={`${player.first_name} ${player.last_name} - Profile`}
            />

            <header className="flex h-[92px] items-center justify-between border-b border-slate-300 bg-white px-6">
                <button
                    type="button"
                    aria-label="Back"
                    className="rounded p-1 text-slate-500 hover:bg-slate-100"
                >
                    <ChevronRight size={18} />
                </button>
                <h1 className="text-2xl font-bold text-[#1b3fa0]">
                    {player.first_name} {player.last_name}
                    {player.club ? ` (${player.club.name})` : ''}
                </h1>
                <button
                    type="button"
                    className="flex items-center gap-1 rounded bg-[#0031a5] px-4 py-2 text-sm font-semibold text-white hover:bg-[#00268a]"
                >
                    Action
                    <ChevronDown size={14} />
                </button>
            </header>

            <div className="flex bg-[#200064]">
                {tabs.map((tab) => (
                    <button
                        key={tab}
                        type="button"
                        className={`flex-1 border-r border-white/10 py-4 text-sm font-bold last:border-r-0 ${
                            tab === 'Profile'
                                ? 'bg-[#1a0050] text-[#f5f000] ring-2 ring-inset ring-[#f5f000]'
                                : 'text-white hover:bg-white/5'
                        }`}
                    >
                        {tab}
                    </button>
                ))}
            </div>

            <div className="relative flex flex-1 flex-col overflow-hidden bg-gradient-to-b from-emerald-900 via-emerald-800 to-emerald-900">
                <div
                    aria-hidden
                    className="pointer-events-none absolute inset-0 flex items-center justify-center opacity-20"
                >
                    <div className="size-40 rounded-full border-2 border-white" />
                    <div className="absolute inset-x-0 top-1/2 h-px bg-white" />
                </div>

                <div className="relative min-h-0 flex-1 overflow-y-auto px-8 py-5">
                    <h2 className="mb-4 text-center text-lg font-bold text-[#f5f000]">
                        Born {formatDob(player.dob)}
                        {age !== null ? ` (Age ${age})` : ''}.{' '}
                        {player.country_code ?? 'Unknown nationality'}.
                    </h2>

                    <div className="grid grid-cols-2 gap-x-8 gap-y-4 lg:grid-cols-4">
                        <AttributeColumn
                            title="Technical"
                            attributes={player.attributes.technical}
                        />
                        <AttributeColumn
                            title="Mental"
                            attributes={player.attributes.mental}
                        />
                        <AttributeColumn
                            title="Physical"
                            attributes={player.attributes.physical}
                        />
                        {player.position === 'GK' && (
                            <AttributeColumn
                                title="Goalkeeping"
                                attributes={player.attributes.goalkeeping}
                            />
                        )}
                    </div>

                    <div className="mt-4 grid grid-cols-2 gap-x-8 gap-y-1 sm:grid-cols-4">
                        {extraAttributes.map((attribute) => (
                            <div
                                key={attribute.label}
                                className="flex items-center justify-between border-b border-white/10 py-1 text-sm"
                            >
                                <span className="font-semibold text-white">
                                    {attribute.label}
                                </span>
                                <span className="font-bold text-orange-400">
                                    {attribute.value}
                                </span>
                            </div>
                        ))}
                    </div>

                    <div className="mt-5">
                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                aria-label="Previous season"
                                className="flex size-6 items-center justify-center rounded bg-[#0031a5] text-white hover:bg-[#00268a]"
                            >
                                <ChevronLeft size={14} />
                            </button>
                            <button
                                type="button"
                                aria-label="Next season"
                                className="flex size-6 items-center justify-center rounded bg-[#0031a5] text-white hover:bg-[#00268a]"
                            >
                                <ChevronRight size={14} />
                            </button>
                            <div className="grid flex-1 grid-cols-9 gap-px overflow-hidden rounded bg-black/30 text-center text-xs font-bold text-slate-800">
                                {statColumns.map((col) => (
                                    <span
                                        key={col}
                                        className="bg-[#c9c9cc] py-1.5"
                                    >
                                        {col}
                                    </span>
                                ))}
                            </div>
                        </div>

                        <div className="mt-1 divide-y divide-white/10">
                            {statRows.map((row) => (
                                <div
                                    key={row.label}
                                    className="grid grid-cols-[2fr_repeat(8,1fr)] items-center py-1 text-sm"
                                >
                                    <span className="font-semibold text-white">
                                        {row.label}
                                    </span>
                                    <span className="text-center font-bold text-orange-400">
                                        {row.apps}
                                    </span>
                                    <span className="text-center font-bold text-orange-400">
                                        {row.gls}
                                    </span>
                                    <span className="text-center font-bold text-orange-400">
                                        {row.asts}
                                    </span>
                                    <span className="text-center font-bold text-orange-400">
                                        {row.mom}
                                    </span>
                                    <span className="text-center font-bold text-white">
                                        {row.pass}
                                    </span>
                                    <span className="text-center font-bold text-white">
                                        {row.tck}
                                    </span>
                                    <span className="text-center font-bold text-white">
                                        {row.drb}
                                    </span>
                                    <span className="flex justify-center">
                                        <span className="rounded-sm bg-[#3b003c] px-2 py-0.5 text-xs text-white">
                                            ----
                                        </span>
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                <p className="relative border-t border-white/10 bg-black/20 py-3 text-center text-base font-bold text-cyan-300">
                    {positionLabel}
                </p>
            </div>

            <div className="flex bg-[#86888a]">
                <button
                    type="button"
                    className="flex-1 border-r border-slate-400 py-4 text-lg font-bold text-slate-900 hover:bg-slate-400/40"
                >
                    Back
                </button>
                <button
                    type="button"
                    disabled
                    className="flex-1 py-4 text-lg font-bold text-slate-500"
                >
                    Next
                </button>
            </div>
        </GameLayout>
    );
}
