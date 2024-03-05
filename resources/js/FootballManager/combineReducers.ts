import { combineReducers } from 'redux';
import playersReducer from "./Modules/Player/Service/PlayerReducers";

const rootReducer = combineReducers({
    playerReducer: playersReducer
});

export type RootState = ReturnType<typeof rootReducer>;
export default rootReducer;
