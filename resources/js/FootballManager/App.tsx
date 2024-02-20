import React from 'react';
import {Provider} from "react-redux";
import {BrowserRouter, Navigate, Route} from "react-router-dom";
import {AppRoute, routes} from "./routes";
import Home from "./Modules/HomeScreen/Pages";
import {store} from "./store";

export const App: React.FC = () => {
    return (
        <React.StrictMode>
            <Provider store={store}>
                <BrowserRouter>
                    <Route path="/" element={<Home />} />
                </BrowserRouter>
            </Provider>
        </React.StrictMode>
    );
}
