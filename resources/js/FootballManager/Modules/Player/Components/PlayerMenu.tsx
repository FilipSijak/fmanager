import React from "react";
import {AppRoute} from "../../../routes";
import PlayerProfile from "./PlayerProfile";
import {NavLink, Outlet, Route, Routes, useMatch} from "react-router-dom";
import {Switch} from "@headlessui/react";

const playerMenu: AppRoute[] = [
    {
        path: '/player/profile',
        Component: PlayerProfile,
        name: 'Profilea',
        childRoutes: []
    }
];

const PlayerMenu: React.FC = () => {
    return (
        <>
            <p>Player menu</p>
            <div>
                <ul>
                    {playerMenu.map((item) => {
                        return <NavLink to={item.path}>{item.name}</NavLink>
                    })}
                </ul>
            </div>
        </>
    );
}

export default PlayerMenu;
