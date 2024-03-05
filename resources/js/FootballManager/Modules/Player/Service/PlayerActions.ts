import {Service} from "../../../Libs/Service";
import {PlayerService} from "./PlayerService";
import {ThunkAction} from "redux-thunk";
import {FETCH_PLAYER, PlayersActionsTypes, PlayerState} from "./PlayerTypes";

export const fetchPlayer = (): ThunkAction<void, PlayerState, unknown, PlayersActionsTypes> =>
    async (dispatch) => {
        return new PlayerService().fetchPlayer().then((response) => {
            dispatch({type: FETCH_PLAYER, payload: response})
        });
    }
