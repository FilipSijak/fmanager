import {produce} from "immer";
import {FETCH_PLAYER, PlayersActionsTypes, PlayerState} from "./PlayerTypes";

const initialPlayersState: PlayerState = {
    from: 0,
    to: 0,
    total: 0,
    next_url: null,
    prev_url: null,
    loading: false
}

export default function playersReducer(
    state: PlayerState = initialPlayersState,
    action: PlayersActionsTypes
): PlayerState {
    return produce(state, (draft) => {
        switch (action.type) {
            case FETCH_PLAYER:

                //draft.players = action.payload.data;

        }
    })
}
