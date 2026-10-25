export type Side = 'home' | 'away';

export type PlayerMatchStats = {
    position: string;
    started: boolean;
    minutes_played: number;
    goals: number;
    assists: number;
    shots: number;
    shots_on_target: number;
    key_passes: number;
    passes_attempted: number;
    passes_completed: number;
    tackles_attempted: number;
    tackles_won: number;
    interceptions: number;
    fouls_committed: number;
    fouls_won: number;
    yellow_cards: number;
    red_cards: number;
    offsides: number;
    saves: number;
    goals_conceded: number;
    clean_sheet: boolean;
    rating: number;
};

export type LivePlayer = {
    player_id: number | null;
    name: string | null;
    position: string;
    status: 'playing' | 'available' | 'substituted' | 'sent_off' | 'empty';
    stats: PlayerMatchStats | null;
};

export type TeamTotals = {
    possession: number;
    chances: number;
    shots_on_target: number;
    key_passes: number;
    passes_attempted: number;
    passes_completed: number;
    tackles_attempted: number;
    tackles_won: number;
    interceptions: number;
    fouls: number;
    yellow_cards: number;
    red_cards: number;
    offsides: number;
    saves: number;
};

export type LiveSide = {
    club_id: number;
    name: string;
    formation_id: number | null;
    mentality: string;
    goals: number;
    substitutions_used: number;
    totals: TeamTotals;
    on_pitch: LivePlayer[];
    bench: LivePlayer[];
    finished: LivePlayer[];
};

export type MatchEvent = {
    minute: number;
    type: 'goal' | 'yellow_card' | 'red_card' | 'substitution';
    team: Side;
    club_id: number;
    player_id: number | null;
    player_name: string | null;
    assist_player_name: string | null;
    replaced_player_name: string | null;
};

export type LiveMatchData = {
    game_id: number;
    minute: number;
    finished: boolean;
    user_side: Side;
    max_substitutions: number;
    home: LiveSide;
    away: LiveSide;
    events: MatchEvent[];
};

/** Formation slot id to the player placed in it, or null for an empty place. */
export type SlotAssignments = Record<string, number | null>;

export type TacticsDraft = {
    formationId: number;
    mentality: string;
    assignments: SlotAssignments;
};
