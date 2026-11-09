import { Head, Link } from '@inertiajs/react';
import { index as contracts } from '@/actions/App/Http/Controllers/PlayerContractController';
import GameLayout from '@/layouts/GameLayout';
import { buttonClass, money } from '../transfers/types';
import type { ContractPage, ContractPlayer } from './types';

export default function RenewContract({
    club,
    date,
    player,
}: ContractPage & { player: ContractPlayer }) {
    return (
        <GameLayout active="Contracts">
            <Head title={`${player.name} – Contract renewal`} />
            <main className="flex-1 overflow-y-auto bg-[#080820] p-6 text-white">
                <div className="mx-auto flex max-w-5xl flex-col gap-6">
                    <h1 className="text-3xl font-bold text-[#f5f000]">
                        Renew {player.name}’s contract
                    </h1>
                    <p>
                        {club.name} · {date}
                    </p>
                    <section className="border border-[#3355dd] p-5">
                        <h2 className="font-bold text-cyan-300">
                            Current contract
                        </h2>
                        <p className="mt-3">
                            {player.starts_at} – {player.ends_at}
                        </p>
                        <p className="mt-2">
                            Weekly salary: {money(player.terms.salary)}
                        </p>
                    </section>
                    <Link href={contracts.url()} className={buttonClass}>
                        All contracts
                    </Link>
                </div>
            </main>
        </GameLayout>
    );
}
