export type SquadPlayer = {
    id: number;
    first_name: string;
    last_name: string;
    position: string;
    country_code: string | null;
    value: number;
    salary: number | null;
};

export type PositionChip = {
    id: string;
    label: string;
};

/** Maps a lineup slot id (e.g. "DC-1") to the id of the player assigned to it. */
export type LineupAssignments = Record<string, number>;
