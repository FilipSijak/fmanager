import {AppRoute} from "../../routes";
import Player from "./Pages/Player";
import PlayerProfile from "./Components/PlayerProfile";
import {Route, Routes} from "react-router-dom";
import React from "react";

const playerMenu: AppRoute[] = [
    {
        path: '/player/profile',
        Component: PlayerProfile,
        name: 'Profile',
        childRoutes: []
    }
];

export const PlayerRoutes: AppRoute[] = [
    {
        path: '/player',
        Component: Player,
        childRoutes: playerMenu
    }
];

