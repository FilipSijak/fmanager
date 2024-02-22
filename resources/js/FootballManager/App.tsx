import React from 'react';
import {Provider} from "react-redux";
import {BrowserRouter, Navigate, Route, Routes} from "react-router-dom";
import {AppRoute, routes} from "./routes";
import Home from "./Modules/HomeScreen/Pages";
import {store} from "./store";

export const App: React.FC = () => {
    return (
        <React.StrictMode>
            <Provider store={store}>
                <BrowserRouter>
                    <Routes>
                        <Route path="/" element={<Home />} />
                    </Routes>
                </BrowserRouter>
            </Provider>
        </React.StrictMode>
    );
}
