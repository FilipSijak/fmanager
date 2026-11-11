import type { ContractTerms } from '../transfers/types';

export type ContractPlayer = {
    id: number;
    name: string;
    position: string;
    contract_id: number;
    starts_at: string;
    ends_at: string;
    expiring: boolean;
    terms: ContractTerms;
};

export type ContractPage = {
    club: { id: number; name: string };
    date: string;
};
