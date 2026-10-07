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

/** Instructions the PUT /api/tactics payload carries alongside the formation, in its snake_case shape. */
export type TeamInstructions = {
    mentality: string;
    pressing: string;
    passing: string;
    tackling: string;
    offside_trap: boolean;
    counter_attack: boolean;
    men_behind_ball: boolean;
    free_kicks_left_player_id: number | null;
    free_kicks_right_player_id: number | null;
    corners_left_player_id: number | null;
    corners_right_player_id: number | null;
    playmaker_player_id: number | null;
};

export type Tactic = TeamInstructions & {
    id: number;
    name: string;
    formation: Formation;
};

export type TacticsOptions = {
    mentalities: string[];
    pressing: string[];
    passing: string[];
    tackling: string[];
};

export type TacticsData = {
    tactic: Tactic;
    formations: Formation[];
    options: TacticsOptions;
};

/** The editable part of a tactic, as edited in the Team Instructions modal and sent to PUT /api/tactics. */
export type TacticsDraft = TeamInstructions & {
    formationId: number;
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
