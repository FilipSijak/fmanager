import axios from 'axios';
import type { PositionChip } from './types';

export function formatMoney(value: number): string {
    return `£${Math.round(value).toLocaleString('en-US')}`;
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

export const POSITION_CHIPS: PositionChip[] = [
    'GK',
    'DL',
    'DR',
    'DC-1',
    'DC-2',
    'ML',
    'MR',
    'MC-1',
    'MC-2',
    'FC-1',
    'FC-2',
    'SUB1',
    'SUB2',
    'SUB3',
    'SUB4',
    'SUB5',
    'SUB6',
    'SUB7',
].map((chip) => ({ id: chip, label: chip.replace(/-\d$/, '') }));

/** Drag payload MIME type used when dragging a position chip onto a player's box. */
export const LINEUP_CHIP_DRAG_TYPE = 'text/plain';
