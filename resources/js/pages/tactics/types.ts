import type { SquadPlayer } from '../squad/types';

export type FormationSlot = {
    slot: string;
    position: string;
    x: number;
    y: number;
    has_arrow: boolean;
};

export type Formation = {
    id: number;
    code: string;
    name: string;
    tactical_tendency: string;
    slots: FormationSlot[];
};

export type Tactic = {
    id: number;
    name: string;
    formation: Formation;
    mentality: string;
    pressing: string;
    passing: string;
};

export type TacticsOptions = {
    mentalities: string[];
    pressing: string[];
    passing: string[];
};

export type TacticsData = {
    tactic: Tactic;
    formations: Formation[];
    options: TacticsOptions;
};

/** The editable part of a tactic, held locally until the user presses Ok. */
export type TacticsDraft = {
    formationId: number;
    mentality: string;
    pressing: string;
    passing: string;
};

/** A squad player placed in one of the lineup slots saved from the Squad page. */
export type LineupPlayer = {
    number: number;
    player: SquadPlayer;
};

export type PitchPlayer = {
    key: string;
    number: number;
    label: string;
    x: number;
    y: number;
    hasArrow: boolean;
};
