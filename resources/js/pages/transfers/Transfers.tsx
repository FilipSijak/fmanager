import { Head, Link, router } from '@inertiajs/react';
import axios from 'axios';
import { type FormEvent, useRef, useState } from 'react';
import { respond } from '@/actions/App/Http/Controllers/Api/TransferManagementController';
import { index } from '@/actions/App/Http/Controllers/TransferManagementController';
import api from '@/api';
import GameLayout from '@/layouts/GameLayout';
import { playerProfile, playerSearch } from '@/routes';
import ContractFields from './ContractFields';
import {
    buttonClass,
    emptyContract,
    fieldClass,
    money,
    type Transfer,
    type TransferAction,
} from './types';

type Props = {
    club: { id: number; name: string };
    transferBudget: number;
    activeTransfers: Transfer[];
    history: {
        data: Transfer[];
        current_page: number;
        last_page: number;
        total: number;
    };
};

const actionLabels: Record<TransferAction, string> = {
    accept: 'Accept offer',
    counter: 'Counteroffer',
    reject: 'Reject offer',
    contract: 'Offer contract',
    withdraw: 'Withdraw offer',
};

function TransferCard({
    transfer,
    transferBudget,
}: {
    transfer: Transfer;
    transferBudget: number;
}) {
    const [action, setAction] = useState<TransferAction | null>(null);
    const [amount, setAmount] = useState(transfer.amount);
    const [terms, setTerms] = useState(transfer.contract ?? emptyContract);
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<string[]>([]);
    const submitting = useRef(false);

    async function submit(event: FormEvent) {
        event.preventDefault();
        if (!action || submitting.current) return;
        submitting.current = true;
        setBusy(true);
        setErrors([]);
        try {
            await api.post(respond.url(transfer.id), {
                action,
                ...(action === 'counter' ? { amount } : {}),
                ...(action === 'contract' ? terms : {}),
            });
            setAction(null);
            router.reload({
                only: ['activeTransfers', 'history', 'transferBudget'],
            });
        } catch (error) {
            setErrors(
                axios.isAxiosError(error)
                    ? (Object.values(
                          error.response?.data.errors ?? {
                              request: [
                                  error.response?.data.message ??
                                      'Unable to update this offer. Please refresh and try again.',
                              ],
                          },
                      ).flat() as string[])
                    : ['Unable to update this offer. Please try again.'],
            );
        } finally {
            submitting.current = false;
            setBusy(false);
        }
    }

    const needsResponse = transfer.actions.some((item) => item !== 'withdraw');

    return (
        <article
            className={`border bg-[#10102c] ${needsResponse ? 'border-[#f5f000]/70' : 'border-[#3355dd]'}`}
        >
            <div className="flex flex-wrap items-start justify-between gap-4 border-b border-[#3355dd]/50 p-5">
                <div>
                    <Link
                        href={playerProfile.url(transfer.player.id)}
                        className="text-xl font-bold text-cyan-300 hover:underline"
                    >
                        {transfer.player.name}
                    </Link>
                    <p className="mt-1 text-sm text-slate-400">
                        {transfer.player.position} ·{' '}
                        {transfer.type === 3
                            ? 'Free transfer'
                            : transfer.type === 2
                              ? 'Loan'
                              : 'Permanent transfer'}{' '}
                        · {transfer.offered_on}
                    </p>
                </div>
                <div className="text-right">
                    <p
                        className={
                            needsResponse
                                ? 'font-bold text-[#f5f000]'
                                : 'text-slate-300'
                        }
                    >
                        {transfer.status_label}
                    </p>
                    {needsResponse && (
                        <p className="mt-1 text-xs text-[#f5f000]">
                            Your response needed
                        </p>
                    )}
                </div>
            </div>
            <div className="flex flex-col gap-4 p-5">
                <div className="flex flex-wrap justify-between gap-3 text-sm">
                    <p>
                        {transfer.seller?.name ?? 'Free agent'}{' '}
                        <span className="text-slate-500">→</span>{' '}
                        {transfer.buyer.name}
                    </p>
                    <p className="font-bold text-[#f5f000]">
                        {money(transfer.amount)}
                        {transfer.installments > 0
                            ? ` / ${transfer.installments} monthly instalments`
                            : ''}
                    </p>
                </div>
                {transfer.contract && (
                    <div className="grid gap-2 text-sm text-slate-300 sm:grid-cols-3">
                        <p>Salary: {money(transfer.contract.salary)}/week</p>
                        <p>
                            Signing fee: {money(transfer.contract.transfer_fee)}
                        </p>
                        <p>
                            Annual base: {money(transfer.contract.salary * 52)}
                        </p>
                        <p>Appearance: {money(transfer.contract.appearance)}</p>
                        <p>
                            Goal / assist: {money(transfer.contract.goal)} /{' '}
                            {money(transfer.contract.assist)}
                        </p>
                        <p>
                            Clean sheet: {money(transfer.contract.clean_sheet)}
                        </p>
                        <p>League title: {money(transfer.contract.league)}</p>
                        <p>Promotion: {money(transfer.contract.promotion)}</p>
                        <p>Cup win: {money(transfer.contract.cup)}</p>
                        <p>Europa League: {money(transfer.contract.el)}</p>
                        <p>Champions League: {money(transfer.contract.cl)}</p>
                    </div>
                )}
                {transfer.status === 4 && transfer.transfer_date && (
                    <p className="text-sm text-cyan-300">
                        Scheduled arrival: {transfer.transfer_date}
                    </p>
                )}
                {transfer.actions.length > 0 && (
                    <div className="flex flex-wrap gap-2">
                        {transfer.actions.map((item) => (
                            <button
                                key={item}
                                type="button"
                                disabled={busy}
                                aria-expanded={action === item}
                                onClick={() => {
                                    setAction(item);
                                    setErrors([]);
                                }}
                                className={buttonClass}
                            >
                                {item === 'contract' && transfer.status === 9
                                    ? 'Counter player terms'
                                    : actionLabels[item]}
                            </button>
                        ))}
                    </div>
                )}
                {action && (
                    <form
                        onSubmit={submit}
                        className="flex flex-col gap-4 border-t border-[#3355dd] pt-5"
                    >
                        <h3 className="font-bold text-[#f5f000]">
                            {actionLabels[action]}
                        </h3>
                        {action === 'counter' && (
                            <label className="flex max-w-sm flex-col gap-2 text-sm">
                                Proposed transfer fee (£)
                                <input
                                    name="amount"
                                    type="number"
                                    min="0"
                                    max="2000000000"
                                    step="1"
                                    value={amount}
                                    required
                                    disabled={busy}
                                    onChange={(event) =>
                                        setAmount(Number(event.target.value))
                                    }
                                    className={fieldClass}
                                />
                            </label>
                        )}
                        {action === 'contract' && (
                            <ContractFields
                                terms={terms}
                                onChange={setTerms}
                                disabled={busy}
                            />
                        )}
                        <p className="text-sm text-slate-300">
                            {action === 'withdraw' || action === 'reject'
                                ? 'This closes the negotiation. You can make a new offer later.'
                                : action === 'accept' && transfer.status === 7
                                  ? 'Agree to the club’s asking price, then submit your player contract.'
                                  : action === 'accept' && transfer.status === 9
                                    ? 'Agree to these player terms and proceed to the medical and paperwork.'
                                    : 'Submit these terms to continue the negotiation.'}
                        </p>
                        {transfer.direction === 'incoming' && (
                            <p className="text-sm text-slate-400">
                                Available transfer budget:{' '}
                                {money(transferBudget)}
                            </p>
                        )}
                        {errors.length > 0 && (
                            <div role="alert" className="text-sm text-red-400">
                                {errors.map((error) => (
                                    <p key={error}>{error}</p>
                                ))}
                            </div>
                        )}
                        <div className="flex gap-2">
                            <button
                                type="submit"
                                disabled={busy}
                                className={buttonClass}
                            >
                                {busy ? 'Submitting…' : 'Confirm'}
                            </button>
                            <button
                                type="button"
                                disabled={busy}
                                onClick={() => setAction(null)}
                                className={buttonClass}
                            >
                                Cancel
                            </button>
                        </div>
                    </form>
                )}
            </div>
        </article>
    );
}

export default function Transfers({
    club,
    transferBudget,
    activeTransfers,
    history,
}: Props) {
    const [tab, setTab] = useState<'all' | 'incoming' | 'outgoing' | 'history'>(
        'all',
    );
    const responseCount = activeTransfers.filter((transfer) =>
        transfer.actions.some((action) => action !== 'withdraw'),
    ).length;
    const visible =
        tab === 'history'
            ? history.data
            : activeTransfers.filter(
                  (transfer) => tab === 'all' || transfer.direction === tab,
              );

    return (
        <GameLayout active="Transfers">
            <Head title={`${club.name} - Transfers`} />
            <main
                className="min-h-screen flex-1 bg-[#000018] px-6 pt-16 pb-8 text-white"
                style={{ fontFamily: "'Courier New', monospace" }}
            >
                <div className="mx-auto flex max-w-6xl flex-col gap-6">
                    <header className="flex flex-wrap items-start justify-between gap-4 border-b border-[#3355dd] pb-5">
                        <div>
                            <p className="text-sm text-cyan-300">{club.name}</p>
                            <h1 className="mt-2 text-3xl font-bold text-[#f5f000]">
                                Transfer centre
                            </h1>
                        </div>
                        <Link href={playerSearch.url()} className={buttonClass}>
                            Find a player
                        </Link>
                    </header>
                    <div className="grid gap-4 sm:grid-cols-3">
                        {[
                            ['Transfer budget', money(transferBudget)],
                            ['Active negotiations', activeTransfers.length],
                            ['Awaiting your response', responseCount],
                        ].map(([label, value]) => (
                            <div
                                key={label}
                                className="border border-[#3355dd] bg-[#10102c] p-4"
                            >
                                <p className="text-sm text-slate-400">
                                    {label}
                                </p>
                                <p className="mt-2 text-2xl font-bold text-[#f5f000]">
                                    {value}
                                </p>
                            </div>
                        ))}
                    </div>
                    <p className="text-sm text-slate-400">
                        Advance the game to receive replies and complete agreed
                        transfers. Incoming means players joining your club;
                        outgoing means players leaving.
                    </p>
                    <nav
                        aria-label="Transfer filters"
                        className="flex flex-wrap gap-2"
                    >
                        {(
                            ['all', 'incoming', 'outgoing', 'history'] as const
                        ).map((item) => (
                            <button
                                key={item}
                                type="button"
                                aria-pressed={tab === item}
                                onClick={() => setTab(item)}
                                className={`${buttonClass} ${tab === item ? 'bg-[#3355dd]/40' : ''}`}
                            >
                                {item === 'all'
                                    ? 'All active'
                                    : item === 'history'
                                      ? `History (${history.total})`
                                      : item === 'incoming'
                                        ? 'Incoming'
                                        : 'Outgoing'}
                            </button>
                        ))}
                    </nav>
                    <div className="flex flex-col gap-4">
                        {visible.length === 0 ? (
                            <div className="border border-dashed border-[#3355dd] p-10 text-center">
                                <p className="text-lg text-slate-300">
                                    {tab === 'history'
                                        ? 'No completed or closed transfers yet.'
                                        : 'No negotiations in this view.'}
                                </p>
                                <p className="mt-3 text-sm text-slate-400">
                                    Search for a player and open their profile
                                    to make an offer.
                                </p>
                            </div>
                        ) : (
                            visible.map((transfer) => (
                                <TransferCard
                                    key={`${transfer.id}-${transfer.status}-${transfer.amount}-${transfer.contract?.salary}`}
                                    transfer={transfer}
                                    transferBudget={transferBudget}
                                />
                            ))
                        )}
                    </div>
                    {tab === 'history' && history.last_page > 1 && (
                        <nav
                            aria-label="Transfer history pages"
                            className="flex items-center justify-between gap-3"
                        >
                            <button
                                type="button"
                                disabled={history.current_page <= 1}
                                onClick={() =>
                                    router.visit(
                                        index.url({
                                            query: {
                                                page: history.current_page - 1,
                                            },
                                        }),
                                        {
                                            preserveState: true,
                                            preserveScroll: true,
                                        },
                                    )
                                }
                                className={buttonClass}
                            >
                                Previous
                            </button>
                            <p className="text-sm text-slate-400">
                                Page {history.current_page} of{' '}
                                {history.last_page}
                            </p>
                            <button
                                type="button"
                                disabled={
                                    history.current_page >= history.last_page
                                }
                                onClick={() =>
                                    router.visit(
                                        index.url({
                                            query: {
                                                page: history.current_page + 1,
                                            },
                                        }),
                                        {
                                            preserveState: true,
                                            preserveScroll: true,
                                        },
                                    )
                                }
                                className={buttonClass}
                            >
                                Next
                            </button>
                        </nav>
                    )}
                </div>
            </main>
        </GameLayout>
    );
}
