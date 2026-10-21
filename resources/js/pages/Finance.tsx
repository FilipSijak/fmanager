import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { type FormEvent, type ReactNode, useRef, useState } from 'react';
import { store as takeOutLoan } from '@/actions/App/Http/Controllers/FinanceLoanController';
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

type Props = {
    club: { id: number; name: string };
    season: { start_date: string; end_date: string };
    finances: FinancialSummary | null;
    loans: Loan[];
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

export default function Finance({
    club,
    season,
    finances,
    loans,
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
