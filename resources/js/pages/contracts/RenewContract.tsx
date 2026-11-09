import { Head, Link, router } from '@inertiajs/react';
import { isAxiosError } from 'axios';
import { type FormEvent, useState } from 'react';
import { store as renewContract } from '@/actions/App/Http/Controllers/Api/PlayerContractController';
import { index as contracts } from '@/actions/App/Http/Controllers/PlayerContractController';
import api from '@/api';
import GameLayout from '@/layouts/GameLayout';
import ContractFields from '../transfers/ContractFields';
import {
    buttonClass,
    type ContractTerms,
    fieldClass,
    money,
} from '../transfers/types';
import type { ContractPage, ContractPlayer } from './types';

export default function RenewContract({
    club,
    date,
    player,
}: ContractPage & { player: ContractPlayer }) {
    const [terms, setTerms] = useState<ContractTerms>(player.terms);
    const [months, setMonths] = useState(36);
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState<string | null>(null);
    const [errors, setErrors] = useState<string[]>([]);
    const [counterSalary, setCounterSalary] = useState<number | null>(null);
    const [accepted, setAccepted] = useState(false);

    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (busy || accepted) return;
        setBusy(true);
        setErrors([]);
        setMessage(null);
        setCounterSalary(null);
        try {
            const response = await api.post(renewContract.url(player.id), {
                ...terms,
                months,
                expected_contract_end: player.ends_at,
            });
            setMessage(response.data.data.message);
            setAccepted(response.data.data.status === 'accepted');
            if (response.data.data.status === 'accepted')
                router.reload({ only: ['player'] });
            setCounterSalary(response.data.data.counter_salary ?? null);
        } catch (error) {
            const messages = isAxiosError(error)
                ? error.response?.data?.errors
                : null;
            setErrors(
                messages
                    ? (Object.values(messages).flat() as string[])
                    : ['Unable to submit the renewal. Please try again.'],
            );
        } finally {
            setBusy(false);
        }
    }

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
                    <form
                        onSubmit={submit}
                        className="flex flex-col gap-5 border border-[#3355dd] p-5"
                    >
                        <label className="flex flex-col gap-2 text-sm">
                            Contract length in months
                            <input
                                type="number"
                                min={6}
                                max={60}
                                step={1}
                                required
                                value={months}
                                disabled={busy || accepted}
                                onChange={(event) =>
                                    setMonths(Number(event.target.value))
                                }
                                className={fieldClass}
                            />
                        </label>
                        <p className="text-sm text-slate-300">
                            6 months to 5 years from {date}. The new expiry must
                            be later than {player.ends_at}. Signing fees are
                            paid immediately; bonuses are additional to the
                            weekly salary.
                        </p>
                        <ContractFields
                            terms={terms}
                            onChange={setTerms}
                            disabled={busy || accepted}
                        />
                        {errors.length > 0 && (
                            <div role="alert" className="text-red-300">
                                {errors.map((error) => (
                                    <p key={error}>{error}</p>
                                ))}
                            </div>
                        )}
                        {message && (
                            <p role="status" className="text-[#39ff14]">
                                {message}
                            </p>
                        )}
                        {counterSalary !== null && (
                            <button
                                type="button"
                                className={buttonClass}
                                onClick={() => {
                                    setTerms({
                                        ...terms,
                                        salary: counterSalary,
                                    });
                                    setCounterSalary(null);
                                    setMessage(
                                        'Requested salary filled in. Submit the offer to confirm these terms.',
                                    );
                                }}
                            >
                                Use player’s requested salary
                            </button>
                        )}
                        <button
                            type="submit"
                            disabled={busy || accepted}
                            className={buttonClass}
                        >
                            {accepted
                                ? 'Contract renewed'
                                : busy
                                  ? 'Submitting…'
                                  : 'Submit renewal offer'}
                        </button>
                    </form>
                    <Link href={contracts.url()} className={buttonClass}>
                        All contracts
                    </Link>
                </div>
            </main>
        </GameLayout>
    );
}
