import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { show as renewContract } from '@/actions/App/Http/Controllers/PlayerContractController';
import GameLayout from '@/layouts/GameLayout';
import { buttonClass, money } from '../transfers/types';
import type { ContractPage, ContractPlayer } from './types';

export default function Contracts({
    club,
    date,
    players,
}: ContractPage & { players: ContractPlayer[] }) {
    const [expiringOnly, setExpiringOnly] = useState(true);
    const visible = players.filter(
        (player) => !expiringOnly || player.expiring,
    );
    return (
        <GameLayout active="Contracts">
            <Head title={`${club.name} – Player contracts`} />
            <main className="flex-1 overflow-y-auto bg-[#080820] p-6 text-white">
                <h1 className="text-3xl font-bold text-[#f5f000]">
                    {club.name} · Player contracts
                </h1>
                <p className="mt-3 text-slate-300">
                    {date} · Renewals for your club are manual. Expiring
                    contracts have six months or less remaining.
                </p>
                <label className="my-5 flex items-center gap-3">
                    <input
                        type="checkbox"
                        checked={expiringOnly}
                        onChange={(event) =>
                            setExpiringOnly(event.target.checked)
                        }
                    />
                    Expiring contracts only
                </label>
                {visible.length === 0 ? (
                    <p>No contracts match this filter.</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-[#20204c]">
                                <tr>
                                    {[
                                        'Player',
                                        'Position',
                                        'Weekly salary',
                                        'Expires',
                                        '',
                                    ].map((label) => (
                                        <th
                                            key={label}
                                            scope="col"
                                            className="p-3"
                                        >
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {visible.map((player) => (
                                    <tr
                                        key={player.id}
                                        className="border-b border-[#3355dd]/40"
                                    >
                                        <th scope="row" className="p-3">
                                            {player.name}
                                        </th>
                                        <td className="p-3">
                                            {player.position}
                                        </td>
                                        <td className="p-3">
                                            {money(player.terms.salary)}
                                        </td>
                                        <td
                                            className={`p-3 ${player.expiring ? 'text-orange-300' : ''}`}
                                        >
                                            {player.ends_at}
                                        </td>
                                        <td className="p-3">
                                            <Link
                                                href={renewContract.url(
                                                    player.id,
                                                )}
                                                className={buttonClass}
                                            >
                                                Renew contract
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </main>
        </GameLayout>
    );
}
