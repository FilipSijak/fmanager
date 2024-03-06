import {array} from "yup";

export const FETCH_PLAYER = 'player@FETCH_PLAYER';

export interface PlayerState {
    from: number;
    to: number;
    total: number;
    next_url: string|null;
    prev_url: string|null;
    loading: boolean;
}

export type Player = {
    name: string
}

export type PlayerResponse = {
    data: Player[]
}

interface FetchPlayer {
    type: typeof FETCH_PLAYER,
    payload: PlayerResponse
}

export type PlayersActionsTypes = FetchPlayer;
