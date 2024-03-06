import {applyMiddleware, configureStore} from "redux";
import {thunk} from "redux-thunk";
import rootReducer from "./combineReducers";

export const store = configureStore(rootReducer, applyMiddleware(thunk));
