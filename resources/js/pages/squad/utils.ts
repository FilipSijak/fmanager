import axios from 'axios';
import type { Formation } from '../tactics/types';
import type { PositionChip, SortOption, SquadPlayer } from './types';

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

/** Bench slot ids; starters use the formation's own slot ids instead. */
export const SUBSTITUTE_SLOT_IDS = [
    'SUB1',
    'SUB2',
    'SUB3',
    'SUB4',
    'SUB5',
    'SUB6',
    'SUB7',
];

export const SUBSTITUTE_POSITION_LABEL = 'SUB';

/** One chip per formation slot (labelled by its position), then the bench. */
export function positionChipsForFormation(
    formation: Formation | null,
): PositionChip[] {
    return [
        ...(formation?.slots.map((slot) => ({
            id: slot.slot,
            label: slot.position,
        })) ?? []),
        ...SUBSTITUTE_SLOT_IDS.map((id) => ({
            id,
            label: SUBSTITUTE_POSITION_LABEL,
        })),
    ];
}

/** Drag payload MIME type used when dragging a position chip onto a player's box. */
export const LINEUP_CHIP_DRAG_TYPE = 'text/plain';

export const SORT_OPTIONS: { value: SortOption; label: string }[] = [
    { value: 'position', label: 'Position' },
    { value: 'value', label: 'Value' },
    { value: 'age', label: 'Age' },
    { value: 'name', label: 'Name / Surname' },
    { value: 'contract_expiry', label: 'Contract Expiry' },
    { value: 'salary', label: 'Salary' },
];

/** Age in full years as of asOfDate (the in-game date, not the real-world date). */
export function ageFromDob(
    dob: string | null,
    asOfDate: string | null,
): number | null {
    if (!dob || !asOfDate) {
        return null;
    }

    const birth = new Date(dob);
    const asOf = new Date(asOfDate);
    let age = asOf.getFullYear() - birth.getFullYear();
    const hasHadBirthdayThisYear =
        asOf.getMonth() > birth.getMonth() ||
        (asOf.getMonth() === birth.getMonth() &&
            asOf.getDate() >= birth.getDate());

    if (!hasHadBirthdayThisYear) {
        age -= 1;
    }

    return age;
}

export function sortPlayers(
    players: SquadPlayer[],
    sortBy: SortOption,
    asOfDate: string | null,
): SquadPlayer[] {
    const sorted = [...players];

    switch (sortBy) {
        case 'position':
            return sorted.sort((a, b) => a.position.localeCompare(b.position));
        case 'value':
            return sorted.sort((a, b) => b.value - a.value);
        case 'salary':
            return sorted.sort((a, b) => (b.salary ?? 0) - (a.salary ?? 0));
        case 'age':
            return sorted.sort(
                (a, b) =>
                    (ageFromDob(a.dob, asOfDate) ?? 0) -
                    (ageFromDob(b.dob, asOfDate) ?? 0),
            );
        case 'name':
            return sorted.sort(
                (a, b) =>
                    a.last_name.localeCompare(b.last_name) ||
                    a.first_name.localeCompare(b.first_name),
            );
        case 'contract_expiry':
            return sorted.sort((a, b) => {
                if (!a.contract_end && !b.contract_end) {
                    return 0;
                }
                if (!a.contract_end) {
                    return 1;
                }
                if (!b.contract_end) {
                    return -1;
                }
                return a.contract_end.localeCompare(b.contract_end);
            });
        default:
            return sorted;
    }
}
