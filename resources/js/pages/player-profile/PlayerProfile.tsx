import { Head, Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import {
    offer,
    index as transfers,
} from '@/actions/App/Http/Controllers/TransferManagementController';
import GameLayout from '@/layouts/GameLayout';
import AttributeColumn from './components/AttributeColumn';
import RecentAppearances from './components/RecentAppearances';
import { usePlayerProfile } from './usePlayerProfile';
import { ageFromDob, formatDob, POSITION_LABELS } from './utils';

type ExtraAttribute = {
    label: string;
    value: string;
};

const extraAttributes: ExtraAttribute[] = [
    { label: 'Preferred Foot', value: 'Right Only' },
    { label: 'Morale', value: 'Superb' },
    { label: 'Condition', value: '100%' },
];

const tabs = [
    'Profile',
    'Injuries & Bans',
    'Contract',
    'Transfer',
    'History',
] as const;

export default function PlayerProfile({ playerId }: { playerId: number }) {
    const { player, instanceDate, loadError, managedClubId } =
        usePlayerProfile(playerId);

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
    const canOffer =
        managedClubId !== null &&
        player.club?.id !== managedClubId &&
        !player.is_retired &&
        !player.is_on_loan;

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
                {managedClubId !== null && (
                    <Link
                        href={canOffer ? offer.url(player.id) : transfers.url()}
                        className="flex items-center gap-1 rounded bg-[#0031a5] px-4 py-2 text-sm font-semibold text-white hover:bg-[#00268a]"
                    >
                        {canOffer ? 'Make offer' : 'Manage transfers'}
                    </Link>
                )}
            </header>

            <div className="flex bg-[#200064]">
                {tabs.map((tab) =>
                    tab === 'Transfer' && managedClubId !== null ? (
                        <Link
                            key={tab}
                            href={
                                canOffer
                                    ? offer.url(player.id)
                                    : transfers.url()
                            }
                            className="flex-1 border-r border-white/10 py-4 text-center text-sm font-bold text-white hover:bg-white/5"
                        >
                            Transfer
                        </Link>
                    ) : (
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
                    ),
                )}
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
                        {[
                            {
                                label: 'Form',
                                value:
                                    player.performance.recent
                                        .map((match) => match.rating.toFixed(1))
                                        .join(' / ') || '—',
                            },
                            ...extraAttributes,
                        ].map((attribute) => (
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

                    <section
                        className="mt-5 overflow-x-auto"
                        aria-label="Season performance"
                    >
                        <h2 className="mb-2 font-bold text-[#f5f000]">
                            Season performance
                        </h2>
                        {player.performance.competitions.length === 0 ? (
                            <p className="py-3 text-sm text-white">
                                No appearances recorded this season.
                            </p>
                        ) : (
                            <table className="w-full text-left text-sm text-white">
                                <thead className="bg-[#c9c9cc] text-slate-900">
                                    <tr>
                                        {[
                                            'Competition',
                                            'Apps',
                                            'Minutes',
                                            'Goals',
                                            'Assists',
                                            'Avg rating',
                                        ].map((label) => (
                                            <th
                                                key={label}
                                                scope="col"
                                                className="px-3 py-2"
                                            >
                                                {label}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {player.performance.competitions.map(
                                        (row) => (
                                            <tr
                                                key={row.id}
                                                className="border-b border-white/10"
                                            >
                                                <th
                                                    scope="row"
                                                    className="px-3 py-2"
                                                >
                                                    {row.name}
                                                </th>
                                                <td className="px-3 py-2">
                                                    {row.appearances}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {row.minutes}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {row.goals}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {row.assists}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {row.average_rating.toFixed(
                                                        1,
                                                    )}
                                                </td>
                                            </tr>
                                        ),
                                    )}
                                </tbody>
                            </table>
                        )}
                    </section>
                    <RecentAppearances
                        appearances={player.performance.recent}
                    />
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
