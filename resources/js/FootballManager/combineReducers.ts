import { combineReducers } from 'redux';

const rootReducer = combineReducers({
    playerReducer: {}
});

export type RootState = ReturnType<typeof rootReducer>;
export default rootReducer;
