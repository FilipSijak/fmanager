import type { PositionChip } from './types';

export function formatMoney(value: number): string {
    return `£${Math.round(value).toLocaleString('en-US')}`;
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
    'SB1',
    'SB2',
    'SB3',
    'SB4',
    'SB5',
    'SB6',
    'SB7',
].map((chip) => ({ id: chip, label: chip.replace(/-\d$/, '') }));

/** Drag payload MIME type used when dragging a position chip onto a player's box. */
export const LINEUP_CHIP_DRAG_TYPE = 'text/plain';
