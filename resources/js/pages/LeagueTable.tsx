import { Head } from '@inertiajs/react';
import { ChevronDown, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import GameLayout from '@/layouts/GameLayout';

type TableRow = {
    position: number;
    positionLabel: string;
    club: string;
    played: number;
    won: number;
    drawn: number;
    lost: number;
    goalsFor: number;
    goalsAgainst: number;
    points: number;
};

const tableRows: TableRow[] = [
    {
        position: 1,
        positionLabel: '1st',
        club: 'Man Utd',
        played: 2,
        won: 2,
        drawn: 0,
        lost: 0,
        goalsFor: 3,
        goalsAgainst: 0,
        points: 6,
    },
    {
        position: 2,
        positionLabel: '2nd',
        club: 'Chelsea',
        played: 2,
        won: 1,
        drawn: 1,
        lost: 0,
        goalsFor: 2,
        goalsAgainst: 0,
        points: 4,
    },
    {
        position: 3,
        positionLabel: '3rd',
        club: 'Aston Villa',
        played: 2,
        won: 1,
        drawn: 1,
        lost: 0,
        goalsFor: 1,
        goalsAgainst: 0,
        points: 4,
    },
    {
        position: 4,
        positionLabel: '4th',
        club: 'Derby',
        played: 2,
        won: 1,
        drawn: 1,
        lost: 0,
        goalsFor: 1,
        goalsAgainst: 0,
        points: 4,
    },
    {
        position: 5,
        positionLabel: '5th',
        club: 'Arsenal',
        played: 1,
        won: 1,
        drawn: 0,
        lost: 0,
        goalsFor: 3,
        goalsAgainst: 1,
        points: 3,
    },
    {
        position: 6,
        positionLabel: '6th',
        club: 'Bolton',
        played: 1,
        won: 1,
        drawn: 0,
        lost: 0,
        goalsFor: 3,
        goalsAgainst: 1,
        points: 3,
    },
    {
        position: 7,
        positionLabel: '7th',
        club: 'Fulham',
        played: 2,
        won: 1,
        drawn: 0,
        lost: 1,
        goalsFor: 4,
        goalsAgainst: 3,
        points: 3,
    },
    {
        position: 8,
        positionLabel: '8th',
        club: 'Everton',
        played: 2,
        won: 1,
        drawn: 0,
        lost: 1,
        goalsFor: 3,
        goalsAgainst: 2,
        points: 3,
    },
    {
        position: 9,
        positionLabel: '9th',
        club: 'Leeds',
        played: 2,
        won: 1,
        drawn: 0,
        lost: 1,
        goalsFor: 3,
        goalsAgainst: 3,
        points: 3,
    },
    {
        position: 10,
        positionLabel: '10th',
        club: 'West Ham',
        played: 2,
        won: 1,
        drawn: 0,
        lost: 1,
        goalsFor: 1,
        goalsAgainst: 1,
        points: 3,
    },
    {
        position: 11,
        positionLabel: '11th',
        club: 'Blackburn',
        played: 2,
        won: 1,
        drawn: 0,
        lost: 1,
        goalsFor: 3,
        goalsAgainst: 4,
        points: 3,
    },
    {
        position: 12,
        positionLabel: '12th',
        club: 'Ipswich',
        played: 2,
        won: 1,
        drawn: 0,
        lost: 1,
        goalsFor: 3,
        goalsAgainst: 4,
        points: 3,
    },
];

const tabs = ['Table', 'Results', 'Fixtures', 'Schedule'] as const;
const statTabs = ['Team Stats', 'Player Stats', 'Referee Stats'] as const;

export default function LeagueTable() {
    const [activeTab, setActiveTab] = useState<(typeof tabs)[number]>('Table');

    return (
        <GameLayout active="Competitions">
            <Head title="League Table" />

            <header className="flex h-[92px] items-center justify-between border-b border-slate-300 bg-white px-6">
                <button
                    type="button"
                    aria-label="Back"
                    className="rounded p-1 text-slate-500 hover:bg-slate-100"
                >
                    <ChevronRight size={18} />
                </button>
                <h1 className="text-3xl font-black tracking-tight text-[#1b3fa0]">
                    English Premier Division
                </h1>
                <button
                    type="button"
                    className="flex items-center gap-1 rounded border border-slate-300 px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                >
                    Print
                    <ChevronDown size={14} />
                </button>
            </header>

            <div className="flex bg-[#200061]">
                {tabs.map((tab) => (
                    <button
                        key={tab}
                        type="button"
                        onClick={() => setActiveTab(tab)}
                        className={`flex-1 border-r border-white/10 py-4 text-sm font-bold last:border-r-0 ${
                            activeTab === tab
                                ? 'bg-[#1a0050] text-[#f5f000] ring-2 ring-inset ring-[#f5f000]'
                                : 'text-white hover:bg-white/5'
                        }`}
                    >
                        {tab}
                    </button>
                ))}
            </div>

            <div className="flex flex-1 flex-col overflow-hidden bg-[#0c0c14]">
                <div className="px-5 pt-4">
                    <button
                        type="button"
                        className="flex items-center gap-2 rounded border border-slate-400 bg-[#c9c9cc] px-4 py-1.5 text-sm font-semibold text-slate-800 hover:bg-slate-300"
                    >
                        View
                        <ChevronDown size={14} />
                    </button>
                </div>

                {activeTab === 'Table' ? (
                    <div className="flex flex-1 flex-col overflow-hidden px-5 pt-4 pb-2">
                        <h2 className="mb-4 text-center text-2xl font-bold text-[#f5f000]">
                            League Table
                        </h2>

                        <div className="min-h-0 flex-1 overflow-y-auto pr-1">
                            <table className="w-full border-separate border-spacing-y-1 text-sm">
                                <thead>
                                    <tr className="text-slate-800">
                                        <th className="w-16" />
                                        <th className="text-left" />
                                        {[
                                            'Pld',
                                            'Won',
                                            'Drn',
                                            'Lst',
                                            'For',
                                            'Ag',
                                        ].map((label) => (
                                            <th
                                                key={label}
                                                className="bg-[#dcdbde] px-3 py-1.5 text-center font-bold"
                                            >
                                                {label}
                                            </th>
                                        ))}
                                        <th className="bg-[#dcdbde] px-3 py-1.5 text-center font-bold">
                                            Pts
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {tableRows.map((row) => (
                                        <tr key={row.club}>
                                            <td className="pr-2">
                                                <span className="inline-flex w-14 items-center justify-center rounded bg-[#101358] px-2 py-1 font-bold text-white">
                                                    {row.positionLabel}
                                                </span>
                                            </td>
                                            <td className="py-1 pr-4 text-lg font-bold whitespace-nowrap text-white">
                                                {row.club}
                                            </td>
                                            <td className="px-3 text-center font-semibold text-white">
                                                {row.played}
                                            </td>
                                            <td className="px-3 text-center font-semibold text-white">
                                                {row.won}
                                            </td>
                                            <td className="px-3 text-center font-semibold text-white">
                                                {row.drawn}
                                            </td>
                                            <td className="px-3 text-center font-semibold text-white">
                                                {row.lost}
                                            </td>
                                            <td className="px-3 text-center font-semibold text-white">
                                                {row.goalsFor}
                                            </td>
                                            <td className="px-3 text-center font-semibold text-white">
                                                {row.goalsAgainst}
                                            </td>
                                            <td className="text-center">
                                                <span className="inline-flex w-10 items-center justify-center rounded bg-[#3b003c] py-1 font-bold text-white">
                                                    {row.points}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                ) : (
                    <div className="flex flex-1 items-center justify-center text-sm font-semibold text-white/50">
                        {activeTab} coming soon
                    </div>
                )}
            </div>

            <div className="flex bg-[#200061] text-sm font-bold text-white">
                {statTabs.map((tab) => (
                    <button
                        key={tab}
                        type="button"
                        className="flex-1 border-r border-white/10 py-3 hover:bg-white/5"
                    >
                        {tab}
                    </button>
                ))}
                <button
                    type="button"
                    className="flex flex-1 items-center justify-center gap-1 border-r border-white/10 py-3 text-cyan-300 hover:bg-white/5"
                >
                    Awards
                    <ChevronRight size={14} />
                </button>
                <button
                    type="button"
                    className="flex flex-1 items-center justify-center gap-1 py-3 text-cyan-300 hover:bg-white/5"
                >
                    History
                    <ChevronRight size={14} />
                </button>
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
