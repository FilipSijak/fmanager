import { Head } from '@inertiajs/react';
import { useState } from 'react';
import GameLayout from '@/layouts/GameLayout';

type NewsItem = {
    id: number;
    is_read: boolean;
    type: string;
    date: string;
    headline: string;
    body: string[];
};

type DashboardData = {
    club: { name: string };
    news: Array<{
        id: number;
        title: string;
        content: string;
        type: string;
        is_read: boolean;
        published_at: string;
    }>;
};
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

function formatNewsDate(date: string): string {
    return new Intl.DateTimeFormat('en-GB', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    }).format(new Date(date));
}

export default function Dashboard({ dashboard }: { dashboard: DashboardData }) {
    const newsItems: NewsItem[] = dashboard.news.map((item) => ({
        id: item.id,
        is_read: item.is_read,
        type: item.type,
        date: formatNewsDate(item.published_at),
        headline: item.title,
        body: [item.content],
    }));
    const [activeTab, setActiveTab] =
        useState<(typeof newsTabs)[number]>('All');
    const [selectedIndex, setSelectedIndex] = useState(0);
    const selected = newsItems[selectedIndex] ?? newsItems[0];

    return (
        <GameLayout active="Continue Game">
            <Head title="Manager Dashboard" />

            <header className="flex h-[92px] items-center justify-center border-b border-black bg-black px-6">
                <h1 className="text-3xl font-black tracking-tight text-[#3355dd]">
                    {dashboard.club.name} News
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
                    {newsItems.length === 0 && (
                        <p className="bg-white px-4 py-6 text-center text-sm font-semibold text-slate-900">
                            There is no news to display.
                        </p>
                    )}
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
                        {selected?.headline ?? 'No news selected'}
                    </h2>
                    <div className="max-w-2xl space-y-4">
                        {selected?.body.map((paragraph) => (
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
