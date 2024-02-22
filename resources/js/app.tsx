import React from 'react';
import ReactDOM from 'react-dom/client';
import {App} from "./FootballManager/App";
import {HttpClient} from "./FootballManager/Libs/HttpClient";

const root = ReactDOM.createRoot(document.getElementById('football-manager-container') as HTMLElement);
root.render(
    <React.StrictMode>
        <App />
    </React.StrictMode>
);
