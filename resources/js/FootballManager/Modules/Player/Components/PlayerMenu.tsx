import React from "react";
import {AppRoute} from "../../../routes";
import PlayerProfile from "./PlayerProfile";
import {NavLink, Route, Routes, useMatch} from "react-router-dom";
import {Switch} from "@headlessui/react";

const playerMenu: AppRoute[] = [
    {
        path: '/player/profile',
        Component: PlayerProfile,
        name: 'Profile'
    }
];

const PlayerMenu: React.FC = () => {
    return (
        <>
            <div>
                <div>Player page</div>
                <ul>
                    {playerMenu.map((item) => {
                        return <NavLink to={item.path}>{item.name}</NavLink>
                    })}
                </ul>
            </div>
            <div>
                <Routes>
                    {playerMenu.map((item) => {
                        return <Route path={item.path} element={item.Component} />
                    })}

                </Routes>
            </div>
        </>
    );
}

export default PlayerMenu;
