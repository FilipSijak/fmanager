export const FETCH_PLAYER = 'player@FETCH_PLAYER';

export interface PlayerState {
    players: Player[]
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
