import { Head, Link, router } from '@inertiajs/react';
import axios from 'axios';
import { type FormEvent, useRef, useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/Api/TransferManagementController';
import { index } from '@/actions/App/Http/Controllers/TransferManagementController';
import api from '@/api';
import GameLayout from '@/layouts/GameLayout';
import { playerProfile } from '@/routes';
import ContractFields from './ContractFields';
import { buttonClass, emptyContract, fieldClass, money } from './types';

type Props = {
    player: {
        id: number;
        name: string;
        position: string;
        value: number;
        club: { id: number; name: string } | null;
        salary: number;
    };
    club: { id: number; name: string };
    transferBudget: number;
};

export default function PlayerOffer({ player, club, transferBudget }: Props) {
    const [amount, setAmount] = useState(player.value);
    const [installments, setInstallments] = useState(0);
    const [terms, setTerms] = useState({
        ...emptyContract,
        salary: Math.max(1000, player.salary),
    });
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<string[]>([]);
    const submitting = useRef(false);
    const isFree = player.club === null;
    const cost = isFree ? terms.transfer_fee : amount;

    async function submit(event: FormEvent) {
        event.preventDefault();
        if (submitting.current) return;
        submitting.current = true;
        setBusy(true);
        setErrors([]);
        try {
            await api.post(store.url(), {
                player_id: player.id,
                transfer_type: isFree ? 3 : 1,
                ...(isFree ? terms : { amount, installments }),
            });
            router.visit(index.url());
        } catch (error) {
            setErrors(
                axios.isAxiosError(error)
                    ? (Object.values(
                          error.response?.data.errors ?? {
                              request: [
                                  error.response?.data.message ??
                                      'Unable to submit this offer. Please try again.',
                              ],
                          },
                      ).flat() as string[])
                    : ['Unable to submit this offer. Please try again.'],
            );
        } finally {
            submitting.current = false;
            setBusy(false);
        }
    }

    return (
        <GameLayout active="Transfers">
            <Head title={`Offer for ${player.name}`} />
            <main
                className="min-h-screen flex-1 bg-[#000018] px-6 pt-16 pb-8 text-white"
                style={{ fontFamily: "'Courier New', monospace" }}
            >
                <div className="mx-auto flex max-w-5xl flex-col gap-6">
                    <div className="flex flex-wrap items-start justify-between gap-4 border-b border-[#3355dd] pb-5">
                        <div>
                            <p className="text-sm text-cyan-300">
                                {club.name} / Recruitment
                            </p>
                            <h1 className="mt-2 text-3xl font-bold text-[#f5f000]">
                                Make a player offer
                            </h1>
                        </div>
                        <Link href={index.url()} className={buttonClass}>
                            Transfer centre
                        </Link>
                    </div>
                    <section className="border border-[#3355dd] bg-[#10102c] p-5">
                        <Link
                            href={playerProfile.url(player.id)}
                            className="text-2xl font-bold text-cyan-300 hover:underline"
                        >
                            {player.name}
                        </Link>
                        <p className="mt-2 text-slate-300">
                            {player.position} ·{' '}
                            {player.club?.name ?? 'Free agent'} · Value{' '}
                            {money(player.value)}
                        </p>
                        <p className="mt-2 text-slate-300">
                            Transfer budget:{' '}
                            <strong className="text-[#f5f000]">
                                {money(transferBudget)}
                            </strong>
                        </p>
                    </section>
                    <form
                        onSubmit={submit}
                        className="flex flex-col gap-6 border border-[#3355dd] p-6"
                    >
                        <h2 className="text-xl font-bold text-[#f5f000]">
                            {isFree
                                ? 'Free transfer — contract offer'
                                : `Permanent transfer — offer to ${player.club?.name}`}
                        </h2>
                        {isFree ? (
                            <ContractFields
                                terms={terms}
                                onChange={setTerms}
                                disabled={busy}
                            />
                        ) : (
                            <fieldset
                                disabled={busy}
                                className="grid gap-5 sm:grid-cols-2"
                            >
                                <label className="flex flex-col gap-2">
                                    Transfer fee (£)
                                    <input
                                        name="amount"
                                        type="number"
                                        min="0"
                                        max="2000000000"
                                        step="1"
                                        required
                                        value={amount}
                                        onChange={(event) =>
                                            setAmount(
                                                Number(event.target.value),
                                            )
                                        }
                                        className={fieldClass}
                                    />
                                </label>
                                <label className="flex flex-col gap-2">
                                    Payment terms
                                    <select
                                        name="installments"
                                        value={installments}
                                        onChange={(event) =>
                                            setInstallments(
                                                Number(event.target.value),
                                            )
                                        }
                                        className={fieldClass}
                                    >
                                        <option value={0}>Pay in full</option>
                                        {[6, 12, 24, 36, 48, 60].map(
                                            (count) => (
                                                <option
                                                    key={count}
                                                    value={count}
                                                >
                                                    {count} monthly instalments
                                                </option>
                                            ),
                                        )}
                                    </select>
                                </label>
                            </fieldset>
                        )}
                        <div className="border-y border-[#3355dd]/60 py-4 text-sm text-slate-300">
                            <p>
                                Budget after this offer:{' '}
                                <strong
                                    className={
                                        cost > transferBudget
                                            ? 'text-red-400'
                                            : 'text-[#39ff14]'
                                    }
                                >
                                    {money(transferBudget - cost)}
                                </strong>
                            </p>
                            <p className="mt-2">
                                {isFree
                                    ? `Three-year contract. Annual base salary: ${money(terms.salary * 52)}. Bonuses are additional.`
                                    : 'You will negotiate the player’s contract after the club agrees the fee.'}
                            </p>
                            <p className="mt-2">
                                Advance the game to receive a response. An offer
                                does not reserve funds.
                            </p>
                        </div>
                        {errors.length > 0 && (
                            <div role="alert" className="text-red-400">
                                {errors.map((error) => (
                                    <p key={error}>{error}</p>
                                ))}
                            </div>
                        )}
                        <div className="flex gap-3">
                            <button
                                type="submit"
                                disabled={busy || cost > transferBudget}
                                className={buttonClass}
                            >
                                {busy ? 'Sending…' : 'Submit offer'}
                            </button>
                            <Link
                                href={playerProfile.url(player.id)}
                                className={buttonClass}
                            >
                                Back to player
                            </Link>
                        </div>
                    </form>
                </div>
            </main>
        </GameLayout>
    );
}
