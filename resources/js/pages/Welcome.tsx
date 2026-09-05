import { Head } from '@inertiajs/react';
import { Bell, ChevronRight, Newspaper, Search, Users } from 'lucide-react';
import GameLayout from '@/layouts/GameLayout';

const news = [
    {
        category: 'Transfer news',
        title: 'Rovers step up pursuit of promising midfield playmaker',
        summary:
            'Club representatives are expected to meet the player’s agent later this week.',
        time: '18 minutes ago',
        accent: 'bg-emerald-400',
    },
    {
        category: 'Premier Division',
        title: 'Title race tightens after a dramatic weekend of results',
        summary:
            'Just four points now separate the top five teams as the season reaches a crucial stage.',
        time: '1 hour ago',
        accent: 'bg-cyan-400',
    },
    {
        category: 'Club update',
        title: 'First-team squad returns to training ahead of United clash',
        summary:
            'The manager welcomed two players back to full training during Tuesday’s session.',
        time: '3 hours ago',
        accent: 'bg-amber-400',
    },
];

const stats = [
    { label: 'League position', value: '4th', note: 'Premier Division' },
    { label: 'Current form', value: 'W W D W', note: 'Unbeaten in 6' },
    { label: 'Transfer budget', value: '£18.4m', note: '£124k wage room' },
];

export default function Welcome() {
    return (
        <GameLayout active="Continue Game">
            <Head title="Manager Dashboard" />

            <div className="min-w-0 flex-1 overflow-y-auto bg-[#eef1f5] text-slate-900">
                <header className="sticky top-0 z-10 flex h-20 items-center justify-between border-b border-slate-200 bg-white/90 px-5 backdrop-blur sm:px-8">
                    <label className="hidden w-full max-w-sm items-center gap-2 rounded-xl bg-slate-100 px-4 py-2.5 text-slate-500 sm:flex">
                        <Search size={18} />
                        <input
                            type="search"
                            placeholder="Search players, clubs, competitions..."
                            className="w-full bg-transparent text-sm outline-none placeholder:text-slate-400"
                        />
                    </label>

                    <div className="ml-auto flex items-center gap-3">
                        <button
                            type="button"
                            aria-label="Notifications"
                            className="relative rounded-xl border border-slate-200 p-2.5 text-slate-600 hover:bg-slate-50"
                        >
                            <Bell size={19} />
                            <span className="absolute -top-1 -right-1 size-2.5 rounded-full border-2 border-white bg-rose-500" />
                        </button>
                        <div className="hidden text-right sm:block">
                            <p className="text-sm font-semibold">Alex Morgan</p>
                            <p className="text-xs text-slate-500">Head Coach</p>
                        </div>
                        <div className="flex size-10 items-center justify-center rounded-full bg-[#101b2d] text-sm font-bold text-white">
                            AM
                        </div>
                    </div>
                </header>

                <main className="mx-auto max-w-7xl p-5 sm:p-8">
                    <div className="mb-7 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                        <div>
                            <p className="text-sm font-semibold text-emerald-600">
                                Tuesday, 25 August 2026
                            </p>
                            <h1 className="mt-1 text-3xl font-bold tracking-tight">
                                Good morning, Alex
                            </h1>
                            <p className="mt-2 text-slate-500">
                                Here’s what’s happening at Riverside FC.
                            </p>
                        </div>
                        <button
                            type="button"
                            className="rounded-xl bg-[#101b2d] px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-slate-800"
                        >
                            Continue to next day
                        </button>
                    </div>

                    <section className="grid gap-4 md:grid-cols-3">
                        {stats.map((stat) => (
                            <div
                                key={stat.label}
                                className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                            >
                                <p className="text-sm font-medium text-slate-500">
                                    {stat.label}
                                </p>
                                <p className="mt-2 text-2xl font-bold tracking-tight">
                                    {stat.value}
                                </p>
                                <p className="mt-1 text-xs font-medium text-emerald-600">
                                    {stat.note}
                                </p>
                            </div>
                        ))}
                    </section>

                    <div className="mt-6 grid gap-6 xl:grid-cols-[1fr_340px]">
                        <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div className="flex items-center justify-between border-b border-slate-100 px-6 py-5">
                                <div className="flex items-center gap-3">
                                    <Newspaper
                                        className="text-emerald-600"
                                        size={21}
                                    />
                                    <h2 className="text-lg font-bold">
                                        Football news
                                    </h2>
                                </div>
                                <button
                                    type="button"
                                    className="text-sm font-semibold text-emerald-600 hover:text-emerald-700"
                                >
                                    View all
                                </button>
                            </div>

                            <div className="divide-y divide-slate-100">
                                {news.map((item) => (
                                    <article
                                        key={item.title}
                                        className="group flex gap-4 px-6 py-5 transition hover:bg-slate-50"
                                    >
                                        <div
                                            className={`mt-1 h-16 w-1 shrink-0 rounded-full ${item.accent}`}
                                        />
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center justify-between gap-3">
                                                <p className="text-xs font-bold tracking-wide text-emerald-600 uppercase">
                                                    {item.category}
                                                </p>
                                                <span className="shrink-0 text-xs text-slate-400">
                                                    {item.time}
                                                </span>
                                            </div>
                                            <h3 className="mt-1 font-bold leading-snug group-hover:text-emerald-700">
                                                {item.title}
                                            </h3>
                                            <p className="mt-1 text-sm leading-6 text-slate-500">
                                                {item.summary}
                                            </p>
                                        </div>
                                        <ChevronRight
                                            className="mt-7 shrink-0 text-slate-300"
                                            size={19}
                                        />
                                    </article>
                                ))}
                            </div>
                        </section>

                        <div className="space-y-6">
                            <section className="rounded-2xl bg-[#101b2d] p-6 text-white shadow-sm">
                                <p className="text-xs font-bold tracking-[0.18em] text-emerald-400 uppercase">
                                    Next fixture
                                </p>
                                <p className="mt-2 text-sm text-slate-400">
                                    Saturday · Premier Division
                                </p>
                                <div className="my-6 flex items-center justify-between">
                                    <div className="text-center">
                                        <div className="mx-auto flex size-14 items-center justify-center rounded-full bg-emerald-400 font-black text-[#101b2d]">
                                            RFC
                                        </div>
                                        <p className="mt-2 text-sm font-semibold">
                                            Riverside
                                        </p>
                                    </div>
                                    <div className="text-center">
                                        <p className="text-xs text-slate-400">
                                            15:00
                                        </p>
                                        <p className="mt-1 text-lg font-black">
                                            VS
                                        </p>
                                    </div>
                                    <div className="text-center">
                                        <div className="mx-auto flex size-14 items-center justify-center rounded-full bg-white font-black text-[#101b2d]">
                                            UTD
                                        </div>
                                        <p className="mt-2 text-sm font-semibold">
                                            United
                                        </p>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    className="w-full rounded-xl bg-white/10 py-3 text-sm font-semibold hover:bg-white/15"
                                >
                                    Match preview
                                </button>
                            </section>

                            <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                                <div className="flex items-center gap-3">
                                    <Users
                                        className="text-emerald-600"
                                        size={21}
                                    />
                                    <h2 className="font-bold">
                                        Squad availability
                                    </h2>
                                </div>
                                <div className="mt-5 h-2 overflow-hidden rounded-full bg-slate-100">
                                    <div className="h-full w-[88%] rounded-full bg-emerald-400" />
                                </div>
                                <div className="mt-3 flex justify-between text-sm">
                                    <span className="font-semibold">
                                        22 available
                                    </span>
                                    <span className="text-slate-500">
                                        3 unavailable
                                    </span>
                                </div>
                            </section>
                        </div>
                    </div>
                </main>
            </div>
        </GameLayout>
    );
}
