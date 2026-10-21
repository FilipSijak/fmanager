import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { type FormEvent, type ReactNode, useRef, useState } from 'react';
import { store as takeOutLoan } from '@/actions/App/Http/Controllers/FinanceLoanController';
import {
    accept as acceptSponsorOffer,
    negotiate as negotiateSponsorOffer,
    reject as rejectSponsorOffer,
} from '@/actions/App/Http/Controllers/SponsorshipOfferController';
import api from '@/api';
import GameLayout from '@/layouts/GameLayout';

type FinancialSummary = {
    balance: number;
    future_balance: number;
    allowed_debt: number;
    transfer_budget: number;
    annual_salary_budget: number;
    annual_player_wages: number;
    remaining_annual_salary_budget: number;
};

type Loan = {
    id: number;
    loan_type: 'cash' | 'mortgage';
    principal: number;
    interest_amount: number;
    total_amount: number;
    installment_count: number;
    started_at: string;
    status: 'active' | 'completed' | 'defaulted' | 'cancelled';
    installments: {
        id: number;
        installment_number: number;
        amount: number;
        due_date: string;
        paid_at: string | null;
    }[];
};

type SponsorOffer = {
    id: number;
    sponsor_name: string;
    sponsor_rank: number;
    annual_amount: number;
    term_seasons: number;
    starts_at: string;
    expires_at: string;
    respond_by: string;
    negotiation_response: 'agreed' | 'counteroffer' | null;
};

type Props = {
    club: { id: number; name: string };
    season: { start_date: string; end_date: string };
    finances: FinancialSummary | null;
    loans: Loan[];
    sponsorOffers: SponsorOffer[];
    sponsors: {
        id: number;
        sponsor_name: string;
        sponsor_rank: number;
        annual_amount: number;
        starts_at: string;
        expires_at: string;
        status: 'active' | 'expired' | 'upcoming';
        paid_this_season: number;
    }[];
    loanOptions: {
        lender: string;
        annual_interest_rate: number;
        maximum_months: number;
    }[];
};

function formatMoney(value: number): string {
    return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        maximumFractionDigits: 0,
        minimumFractionDigits: 0,
    }).format(value);
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('en-GB', { dateStyle: 'medium' }).format(
        new Date(`${value.slice(0, 10)}T12:00:00`),
    );
}

function Panel({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="border border-[#1f3a1f] bg-[#04120a]">
            <h2 className="border-b border-[#1f3a1f] bg-[#08210f] px-4 py-2 text-sm font-bold tracking-widest text-[#39ff14] uppercase">
                {title}
            </h2>
            <div className="p-4">{children}</div>
        </section>
    );
}

function Stat({ label, value }: { label: string; value: number }) {
    return (
        <div className="border border-[#1f3a1f] bg-[#020a05] p-3">
            <p className="text-xs text-[#5fae5f] uppercase">{label}</p>
            <p
                className={`mt-1 text-xl font-bold ${value < 0 ? 'text-red-400' : 'text-[#f5f000]'}`}
            >
                {formatMoney(value)}
            </p>
        </div>
    );
}

function LoanDetails({ loan }: { loan: Loan }) {
    const installments = [...loan.installments].sort(
        (a, b) => a.installment_number - b.installment_number,
    );
    const unpaid = installments.filter(
        (installment) => installment.paid_at === null,
    );
    const outstanding = unpaid.reduce(
        (total, installment) => total + installment.amount,
        0,
    );

    return (
        <article className="border border-[#1f3a1f] p-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h3 className="font-bold text-[#f5f000]">
                    {loan.loan_type === 'mortgage'
                        ? 'Construction mortgage'
                        : 'Cash loan'}{' '}
                    #{loan.id}
                </h3>
                <span className="text-xs uppercase">{loan.status}</span>
            </div>
            <dl className="mt-3 grid gap-3 text-sm sm:grid-cols-3">
                <div>
                    <dt className="text-[#5fae5f]">Borrowed</dt>
                    <dd>{formatMoney(loan.principal)}</dd>
                </div>
                <div>
                    <dt className="text-[#5fae5f]">Total Interest</dt>
                    <dd>{formatMoney(loan.interest_amount)}</dd>
                </div>
                <div>
                    <dt className="text-[#5fae5f]">Total Repayable</dt>
                    <dd>{formatMoney(loan.total_amount)}</dd>
                </div>
                <div>
                    <dt className="text-[#5fae5f]">Started</dt>
                    <dd>{formatDate(loan.started_at)}</dd>
                </div>
                <div>
                    <dt className="text-[#5fae5f]">Unpaid Installments</dt>
                    <dd>
                        {unpaid.length} / {loan.installment_count}
                    </dd>
                </div>
                <div>
                    <dt className="text-[#5fae5f]">Unpaid Amount</dt>
                    <dd>{formatMoney(outstanding)}</dd>
                </div>
            </dl>
            <details className="mt-3 text-sm">
                <summary className="cursor-pointer text-[#f5f000]">
                    Repayment schedule
                </summary>
                <div className="mt-2 overflow-x-auto">
                    <table className="w-full text-left text-xs">
                        <thead className="text-[#5fae5f]">
                            <tr>
                                <th className="p-2">Installment</th>
                                <th className="p-2">Due</th>
                                <th className="p-2">Amount</th>
                                <th className="p-2">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {installments.map((installment) => (
                                <tr
                                    key={installment.id}
                                    className="border-t border-[#1f3a1f]"
                                >
                                    <td className="p-2">
                                        {installment.installment_number}
                                    </td>
                                    <td className="p-2">
                                        {formatDate(installment.due_date)}
                                    </td>
                                    <td className="p-2">
                                        {formatMoney(installment.amount)}
                                    </td>
                                    <td className="p-2">
                                        {installment.paid_at
                                            ? 'Paid ' +
                                              formatDate(installment.paid_at)
                                            : 'Unpaid'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </details>
        </article>
    );
}

const fieldClass =
    'w-full border border-[#3355dd] bg-black px-3 py-2 text-white focus:outline-2 focus:outline-[#f5f000]';

function SponsorOfferCard({ offer }: { offer: SponsorOffer }) {
    const [counterAmount, setCounterAmount] = useState(
        String(Math.min(30000000, Math.ceil(offer.annual_amount * 1.05))),
    );
    const [busy, setBusy] = useState(false);
    const busyRef = useRef(false);
    const [error, setError] = useState<string | null>(null);
    const [message, setMessage] = useState<string | null>(null);

    async function respond(action: 'accept' | 'reject' | 'negotiate') {
        if (busyRef.current) return;
        busyRef.current = true;
        setBusy(true);
        setError(null);
        setMessage(null);
        const endpoints = {
            accept: acceptSponsorOffer,
            reject: rejectSponsorOffer,
            negotiate: negotiateSponsorOffer,
        };
        try {
            const response = await api.post(
                endpoints[action].url(offer.id),
                action === 'negotiate'
                    ? { annual_amount: Number(counterAmount) }
                    : {},
            );
            if (action === 'negotiate') {
                setCounterAmount(
                    String(
                        Math.min(
                            30000000,
                            Math.ceil(response.data.data.annual_amount * 1.05),
                        ),
                    ),
                );
                setMessage(
                    response.data.data.negotiation_response === 'agreed'
                        ? 'The sponsor agreed to your annual amount. Accept the offer to sign the contract.'
                        : 'The sponsor returned a counter-offer. You can accept it, reject it, or negotiate again.',
                );
            }
        } catch (failure) {
            const data = axios.isAxiosError<{
                error?: string;
                message?: string;
                errors?: Record<string, string[]>;
            }>(failure)
                ? failure.response?.data
                : undefined;
            setError(
                data?.errors?.annual_amount?.join(' ') ??
                    data?.error ??
                    data?.message ??
                    'Unable to respond to this sponsorship offer.',
            );
            busyRef.current = false;
            setBusy(false);
            return;
        }
        router.reload({
            only: ['sponsorOffers', 'sponsors'],
            onFinish: () => {
                busyRef.current = false;
                setBusy(false);
            },
        });
    }

    return (
        <article className="flex flex-col gap-4 border border-[#f5f000]/50 bg-[#04120a] p-4">
            <div className="flex flex-wrap justify-between gap-2">
                <h3 className="font-bold text-[#f5f000]">
                    {offer.sponsor_name}{' '}
                    <span className="text-xs font-normal text-[#5fae5f]">
                        Rank {offer.sponsor_rank}/20
                    </span>
                </h3>
                <span className="text-sm text-[#f5f000]">
                    {formatMoney(offer.annual_amount)} per season
                </span>
            </div>
            <p className="text-sm">
                {offer.term_seasons} season{offer.term_seasons === 1 ? '' : 's'}{' '}
                · {formatDate(offer.starts_at)} to{' '}
                {formatDate(offer.expires_at)}
            </p>
            <p className="text-xs text-[#5fae5f]">
                Respond by {formatDate(offer.respond_by)}. Payment starts with
                the new contract.
            </p>
            {message && (
                <p role="status" className="text-sm text-[#f5f000]">
                    {message}
                </p>
            )}
            {error && (
                <p role="alert" className="text-sm text-red-400">
                    {error}
                </p>
            )}
            <div className="flex gap-3">
                <button
                    type="button"
                    disabled={busy}
                    onClick={() => void respond('accept')}
                    className="border border-[#39ff14] px-4 py-2 font-bold text-[#39ff14] hover:bg-white/5 disabled:opacity-50"
                >
                    {busy ? 'Processing…' : 'Accept'}
                </button>
                <button
                    type="button"
                    disabled={busy}
                    onClick={() => void respond('reject')}
                    className="border border-red-400 px-4 py-2 text-red-400 hover:bg-white/5 disabled:opacity-50"
                >
                    Reject
                </button>
            </div>
            {offer.annual_amount < 30000000 && (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        void respond('negotiate');
                    }}
                >
                    <fieldset
                        disabled={busy}
                        className="flex flex-wrap items-end gap-3 disabled:opacity-50"
                    >
                        <label
                            htmlFor={`sponsor-counter-${offer.id}`}
                            className="flex flex-col gap-2 text-sm"
                        >
                            Counter-offer (£ per season)
                            <input
                                id={`sponsor-counter-${offer.id}`}
                                type="number"
                                min={offer.annual_amount + 1}
                                max="30000000"
                                step="1"
                                required
                                value={counterAmount}
                                onChange={(event) =>
                                    setCounterAmount(event.target.value)
                                }
                                className={fieldClass}
                            />
                        </label>
                        <button
                            type="submit"
                            className="border border-[#f5f000] px-4 py-2 text-[#f5f000] hover:bg-white/5"
                        >
                            Negotiate
                        </button>
                    </fieldset>
                </form>
            )}
        </article>
    );
}

export default function Finance({
    club,
    season,
    finances,
    loans,
    sponsors,
    sponsorOffers,
    loanOptions,
}: Props) {
    const [amount, setAmount] = useState('');
    const [lengthMonths, setLengthMonths] = useState('12');
    const [lender, setLender] = useState('bank');
    const [submitting, setSubmitting] = useState(false);
    const submittingRef = useRef(false);
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [loanError, setLoanError] = useState<string | null>(null);
    const [loanSuccess, setLoanSuccess] = useState<string | null>(null);
    const selectedOption = loanOptions.find(
        (option) => option.lender === lender,
    );
    const maximumMonths = selectedOption?.maximum_months;

    async function submitLoan(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (submittingRef.current) return;
        submittingRef.current = true;
        setSubmitting(true);
        setErrors({});
        setLoanError(null);
        setLoanSuccess(null);

        try {
            await api.post(takeOutLoan.url(), {
                amount: Number(amount),
                length_months: Number(lengthMonths),
                lender,
            });
        } catch (failure) {
            const data = axios.isAxiosError<{
                errors?: Record<string, string[]>;
                error?: string;
                message?: string;
            }>(failure)
                ? failure.response?.data
                : undefined;
            setErrors(data?.errors ?? {});
            setLoanError(
                data?.error ??
                    data?.message ??
                    'Unable to take out this loan. Please try again.',
            );
            submittingRef.current = false;
            setSubmitting(false);
            return;
        }

        setAmount('');
        setLoanSuccess('Loan approved. Updating your finances…');
        router.reload({
            only: ['finances', 'loans'],
            onSuccess: () =>
                setLoanSuccess('Loan approved and funds received.'),
            onError: () =>
                setLoanError(
                    'The loan was approved, but the figures could not be refreshed. Reload this page to see your balance.',
                ),
            onFinish: () => {
                submittingRef.current = false;
                setSubmitting(false);
            },
        });
    }

    return (
        <GameLayout active="Nations & Clubs">
            <Head title={`${club.name} - Finance`} />
            <main
                className="min-h-screen flex-1 bg-black p-6 text-[#c8ffb0] sm:p-10"
                style={{ fontFamily: "'Courier New', ui-monospace, monospace" }}
            >
                <div className="mx-auto flex max-w-6xl flex-col gap-6">
                    <header className="text-center">
                        <p className="text-xs tracking-widest text-[#5fae5f] uppercase">
                            {club.name}
                        </p>
                        <h1 className="mt-1 text-2xl font-bold tracking-widest text-[#39ff14] uppercase">
                            Finance Center
                        </h1>
                        <p className="mt-1 text-xs text-[#5fae5f]">
                            Season {season.start_date.slice(0, 4)}/
                            {season.end_date.slice(2, 4)}
                        </p>
                    </header>

                    {finances ? (
                        <>
                            <Panel title="Overall Finances">
                                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                    <Stat
                                        label="Bank Balance"
                                        value={finances.balance}
                                    />
                                    <Stat
                                        label="Future Balance"
                                        value={finances.future_balance}
                                    />
                                    <Stat
                                        label="Allowed Debt"
                                        value={finances.allowed_debt}
                                    />
                                    <Stat
                                        label="Transfer Budget"
                                        value={finances.transfer_budget}
                                    />
                                </div>
                            </Panel>
                            <Panel title="Annual Player Wages">
                                <div className="grid gap-3 sm:grid-cols-3">
                                    <Stat
                                        label="Annual Wage Budget"
                                        value={finances.annual_salary_budget}
                                    />
                                    <Stat
                                        label="Annual Player Wages"
                                        value={finances.annual_player_wages}
                                    />
                                    <Stat
                                        label="Remaining Annual Budget"
                                        value={
                                            finances.remaining_annual_salary_budget
                                        }
                                    />
                                </div>
                            </Panel>
                        </>
                    ) : (
                        <p role="alert">
                            Financial information is unavailable for this club.
                        </p>
                    )}

                    <Panel title="Sponsorship Offers">
                        {sponsorOffers.length === 0 ? (
                            <p className="text-sm">
                                No active sponsorship offer. Sponsors approach
                                during the final six months of your current
                                contract.
                            </p>
                        ) : (
                            <div className="flex flex-col gap-4">
                                {sponsorOffers.map((offer) => (
                                    <SponsorOfferCard
                                        key={offer.id}
                                        offer={offer}
                                    />
                                ))}
                            </div>
                        )}
                    </Panel>

                    <Panel title="Sponsors">
                        <p className="mb-4 text-xs text-[#5fae5f]">
                            Sponsorship is paid once per year at season start
                            while the contract is active.
                        </p>
                        {sponsors.length === 0 ? (
                            <p className="text-sm">
                                Your club has no sponsorship contracts.
                            </p>
                        ) : (
                            <div className="flex flex-col gap-4">
                                {sponsors.map((sponsor) => (
                                    <article
                                        key={sponsor.id}
                                        className="border border-[#1f3a1f] p-3"
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <h3 className="font-bold text-[#f5f000]">
                                                {sponsor.sponsor_name}
                                                <span className="ml-2 text-xs font-normal text-[#5fae5f]">
                                                    Rank {sponsor.sponsor_rank}
                                                    /20
                                                </span>
                                            </h3>
                                            <span className="text-xs uppercase">
                                                {sponsor.status}
                                            </span>
                                        </div>
                                        <dl className="mt-3 grid gap-3 text-sm sm:grid-cols-4">
                                            <div>
                                                <dt className="text-[#5fae5f]">
                                                    Annual Value
                                                </dt>
                                                <dd>
                                                    {formatMoney(
                                                        sponsor.annual_amount,
                                                    )}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-[#5fae5f]">
                                                    Starts
                                                </dt>
                                                <dd>
                                                    {formatDate(
                                                        sponsor.starts_at,
                                                    )}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-[#5fae5f]">
                                                    Expires
                                                </dt>
                                                <dd>
                                                    {formatDate(
                                                        sponsor.expires_at,
                                                    )}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-[#5fae5f]">
                                                    Received This Season
                                                </dt>
                                                <dd>
                                                    {formatMoney(
                                                        sponsor.paid_this_season,
                                                    )}
                                                </dd>
                                            </div>
                                        </dl>
                                    </article>
                                ))}
                            </div>
                        )}
                    </Panel>

                    <Panel title="Loans">
                        {loans.length === 0 ? (
                            <p className="text-sm">Your club has no loans.</p>
                        ) : (
                            <div className="flex flex-col gap-4">
                                {loans.map((loan) => (
                                    <LoanDetails key={loan.id} loan={loan} />
                                ))}
                            </div>
                        )}
                    </Panel>

                    {finances && (
                        <Panel title="Take Out a Cash Loan">
                            <form
                                onSubmit={(event) => void submitLoan(event)}
                                className="flex flex-col gap-4"
                            >
                                <fieldset
                                    disabled={submitting}
                                    className="grid gap-4 disabled:opacity-60 sm:grid-cols-3"
                                >
                                    <label
                                        htmlFor="loan-amount"
                                        className="flex flex-col gap-2 text-sm"
                                    >
                                        Amount (£)
                                        <input
                                            id="loan-amount"
                                            type="number"
                                            min="1"
                                            step="1"
                                            required
                                            value={amount}
                                            onChange={(event) =>
                                                setAmount(event.target.value)
                                            }
                                            aria-invalid={Boolean(
                                                errors.amount,
                                            )}
                                            className={fieldClass}
                                        />
                                        {errors.amount && (
                                            <span className="text-red-400">
                                                {errors.amount.join(' ')}
                                            </span>
                                        )}
                                    </label>
                                    <label
                                        htmlFor="loan-lender"
                                        className="flex flex-col gap-2 text-sm"
                                    >
                                        Lender
                                        <select
                                            id="loan-lender"
                                            value={lender}
                                            onChange={(event) => {
                                                setLender(event.target.value);
                                                const option = loanOptions.find(
                                                    (option) =>
                                                        option.lender ===
                                                        event.target.value,
                                                );
                                                if (
                                                    option &&
                                                    Number(lengthMonths) >
                                                        option.maximum_months
                                                )
                                                    setLengthMonths(
                                                        String(
                                                            option.maximum_months,
                                                        ),
                                                    );
                                            }}
                                            className={fieldClass}
                                        >
                                            {loanOptions.map((option) => (
                                                <option
                                                    key={option.lender}
                                                    value={option.lender}
                                                >
                                                    {option.lender === 'bank'
                                                        ? 'Bank'
                                                        : 'Loan Sharks'}
                                                </option>
                                            ))}
                                        </select>
                                        {selectedOption && (
                                            <span className="text-xs text-[#5fae5f]">
                                                {Math.round(
                                                    selectedOption.annual_interest_rate *
                                                        100,
                                                )}
                                                % annual interest · up to{' '}
                                                {maximumMonths} months
                                            </span>
                                        )}
                                        {errors.lender && (
                                            <span className="text-red-400">
                                                {errors.lender.join(' ')}
                                            </span>
                                        )}
                                    </label>
                                    <label
                                        htmlFor="loan-length"
                                        className="flex flex-col gap-2 text-sm"
                                    >
                                        Length (months)
                                        <input
                                            id="loan-length"
                                            type="number"
                                            min="1"
                                            max={maximumMonths}
                                            step="1"
                                            required
                                            value={lengthMonths}
                                            onChange={(event) =>
                                                setLengthMonths(
                                                    event.target.value,
                                                )
                                            }
                                            aria-invalid={Boolean(
                                                errors.length_months,
                                            )}
                                            className={fieldClass}
                                        />
                                        {errors.length_months && (
                                            <span className="text-red-400">
                                                {errors.length_months.join(' ')}
                                            </span>
                                        )}
                                    </label>
                                </fieldset>
                                {loanError && (
                                    <p
                                        role="alert"
                                        className="text-sm text-red-400"
                                    >
                                        {loanError}
                                    </p>
                                )}
                                {loanSuccess && (
                                    <p
                                        role="status"
                                        className="text-sm text-[#f5f000]"
                                    >
                                        {loanSuccess}
                                    </p>
                                )}
                                <button
                                    type="submit"
                                    disabled={submitting}
                                    className="self-start border border-[#f5f000] px-5 py-2 font-bold text-[#f5f000] hover:bg-[#f5f000]/10 disabled:opacity-60"
                                >
                                    {submitting
                                        ? 'Processing…'
                                        : 'Take Out Loan'}
                                </button>
                            </form>
                        </Panel>
                    )}
                </div>
            </main>
        </GameLayout>
    );
}
