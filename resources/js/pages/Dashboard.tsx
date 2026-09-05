import { Head } from '@inertiajs/react';
import { useState } from 'react';
import GameLayout from '@/layouts/GameLayout';

type NewsItem = {
    date: string;
    headline: string;
    body: string[];
};

const newsItems: NewsItem[] = [
    {
        date: 'Tue 31st Jul EVE',
        headline: 'Riverside scouting completed',
        body: [
            'Alex Morgan normally prefers Bristol City to play a possession-based 4-3-3 formation.',
            'Bristol City have a well-balanced squad.',
            'Marcus Bell is a solid defender who marshals the back line with composure.',
        ],
    },
    {
        date: 'Tue 31st Jul EVE',
        headline: 'Bristol seal Reyes deal',
        body: [
            'Bristol City have completed the signing of winger Diego Reyes on a three-year contract.',
            'The move is reported to be worth an initial £2.1m, rising to £2.8m with add-ons.',
        ],
    },
    {
        date: 'Sun 29th Jul EVE',
        headline: 'Riverside accepts friendly proposal',
        body: [
            'Riverside FC have accepted a proposal to play a pre-season friendly against Bristol City.',
            'The match is expected to take place at Riverside Park two weeks before the season starts.',
        ],
    },
    {
        date: 'Sun 29th Jul PM',
        headline: 'Martin Silva selected for Brazil match',
        body: [
            'Martin Silva has been called up to the Brazil squad for their upcoming friendly fixture.',
            'The midfielder will link up with the national team after Riverside’s next league game.',
        ],
    },
    {
        date: 'Sun 29th Jul PM',
        headline: 'Diaz selected for Colombia match',
        body: [
            'Diaz has been named in the Colombia squad ahead of their upcoming qualifier.',
            'Riverside will be without the defender for one league fixture as a result.',
        ],
    },
];

const newsTabs = [
    'All',
    'Messages',
    'Competitions',
    'Injuries and Bans',
] as const;
const bottomTabs = [
    'Contracts and Media',
    'Transfers',
    'Jobs',
    'Records',
] as const;

export default function Dashboard() {
    const [activeTab, setActiveTab] =
        useState<(typeof newsTabs)[number]>('All');
    const [selectedIndex, setSelectedIndex] = useState(0);
    const selected = newsItems[selectedIndex];

    return (
        <GameLayout active="Continue Game">
            <Head title="Manager Dashboard" />

            <header className="flex h-[92px] items-center justify-center border-b border-black bg-black px-6">
                <h1 className="text-3xl font-black tracking-tight text-[#3355dd]">
                    Alex Morgan News
                </h1>
            </header>

            <div className="flex bg-[#200061]">
                {newsTabs.map((tab) => (
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
                <div className="overflow-y-auto">
                    {newsItems.map((item, index) => (
                        <button
                            key={`${item.date}-${item.headline}`}
                            type="button"
                            onClick={() => setSelectedIndex(index)}
                            className={`flex w-full text-left text-sm font-semibold ${
                                index === selectedIndex
                                    ? ''
                                    : index % 2 === 0
                                      ? 'bg-white'
                                      : 'bg-[#d9d9d9]'
                            }`}
                        >
                            <span className="w-36 shrink-0 bg-[#101358] px-3 py-2 text-white">
                                {item.date}
                            </span>
                            <span
                                className={`flex-1 px-3 py-2 ${
                                    index === selectedIndex
                                        ? 'bg-[#a41c1c] text-white'
                                        : 'text-slate-900'
                                }`}
                            >
                                {item.headline}
                            </span>
                        </button>
                    ))}
                </div>

                <div className="flex items-center justify-between border-t border-b border-black/40 bg-[#c9c9cc] px-4 py-2">
                    <p className="text-sm font-semibold text-slate-700">
                        Filter :
                    </p>
                    <button
                        type="button"
                        disabled
                        className="rounded border border-slate-400 bg-[#b7b7ba] px-3 py-1 text-xs font-semibold text-slate-500"
                    >
                        Next Unread
                    </button>
                </div>

                <div className="flex flex-1 flex-col items-center overflow-y-auto bg-gradient-to-b from-[#1a1420] to-[#0c0c14] px-8 py-6">
                    <h2 className="mb-4 text-center text-xl font-bold text-[#f5f000]">
                        {selected.headline}
                    </h2>
                    <div className="max-w-2xl space-y-4">
                        {selected.body.map((paragraph) => (
                            <p
                                key={paragraph}
                                className="text-center text-base leading-relaxed font-semibold text-white"
                            >
                                {paragraph}
                            </p>
                        ))}
                    </div>
                </div>
            </div>

            <div className="flex bg-[#200061] text-sm font-bold text-white">
                {bottomTabs.map((tab) => (
                    <button
                        key={tab}
                        type="button"
                        className="flex-1 border-r border-white/10 py-3 last:border-r-0 hover:bg-white/5"
                    >
                        {tab}
                    </button>
                ))}
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
                    className="flex-1 py-4 text-lg font-bold text-slate-900 hover:bg-slate-400/40"
                >
                    Next
                </button>
            </div>
        </GameLayout>
    );
}
