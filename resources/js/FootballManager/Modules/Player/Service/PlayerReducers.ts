import {produce} from "immer";
import {FETCH_PLAYER, PlayersActionsTypes, PlayerState} from "./PlayerTypes";

const initialPlayersState: PlayerState = {
    players: []
}

export default function playersReducer(
    state = initialPlayersState,
    action: PlayersActionsTypes
) {
    return produce(state, (draft) => {
        switch (action.type) {
            case FETCH_PLAYER:
                //draft.players = action.payload.data;
            default:

        }
    })
}
