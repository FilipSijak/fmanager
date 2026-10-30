export type ContractTerms = {
    salary: number;
    transfer_fee: number;
    appearance: number;
    goal: number;
    assist: number;
    clean_sheet: number;
    league: number;
    promotion: number;
    cup: number;
    el: number;
    cl: number;
};

export type TransferAction =
    | 'accept'
    | 'reject'
    | 'counter'
    | 'contract'
    | 'withdraw';

export type Transfer = {
    id: number;
    player: { id: number; name: string; position: string };
    buyer: { id: number; name: string };
    seller: { id: number; name: string } | null;
    direction: 'incoming' | 'outgoing';
    type: number;
    status: number;
    status_label: string;
    amount: number;
    installments: number;
    contract: ContractTerms | null;
    offered_on: string | null;
    transfer_date: string | null;
    actions: TransferAction[];
};

export const emptyContract: ContractTerms = {
    salary: 1000,
    transfer_fee: 0,
    appearance: 0,
    goal: 0,
    assist: 0,
    clean_sheet: 0,
    league: 0,
    promotion: 0,
    cup: 0,
    el: 0,
    cl: 0,
};

export const money = (value: number) =>
    new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        maximumFractionDigits: 0,
    }).format(value);

export const buttonClass =
    'border border-[#3355dd] px-4 py-2 font-bold text-[#f5f000] hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-[#f5f000] disabled:cursor-wait disabled:opacity-40';

export const fieldClass =
    'w-full border border-[#3355dd] bg-black px-3 py-2 text-white focus:outline-2 focus:outline-[#f5f000]';
