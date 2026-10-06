import axios from 'axios';
import type { BuildableCategory } from './types';

export const monoFont = {
    fontFamily: "'Courier New', ui-monospace, monospace",
};

export const CAPACITY_STEP = 1000;
export const DEFAULT_MORTGAGE_YEARS = 2;

export const STAND_LABELS: Record<string, string> = {
    north: 'North Stand',
    east: 'East Stand',
    south: 'South Stand',
    west: 'West Stand',
    north_east: 'North-East Corner',
    south_east: 'South-East Corner',
    south_west: 'South-West Corner',
    north_west: 'North-West Corner',
};

export const SIZE_OPTIONS: {
    value: 1 | 2 | 3;
    key: 'small' | 'medium' | 'large';
    label: string;
}[] = [
    { value: 1, key: 'small', label: 'Small' },
    { value: 2, key: 'medium', label: 'Medium' },
    { value: 3, key: 'large', label: 'Large' },
];

export function formatMoney(value: number): string {
    return `£${Math.round(value).toLocaleString('en-US')}`;
}

export function formatNumber(value: number): string {
    return value.toLocaleString('en-US');
}

export function extractErrorMessage(error: unknown): string {
    if (
        axios.isAxiosError(error) &&
        typeof error.response?.data?.error === 'string'
    ) {
        return error.response.data.error;
    }

    return 'Something went wrong. Please try again.';
}

export function sizeLabel(size: 1 | 2 | 3): string {
    return (
        SIZE_OPTIONS.find((option) => option.value === size)?.label ?? 'Unknown'
    );
}

export function availableSizesForCategory(category: BuildableCategory) {
    return SIZE_OPTIONS.filter(
        (option) => category.costs[option.key] !== undefined,
    );
}
