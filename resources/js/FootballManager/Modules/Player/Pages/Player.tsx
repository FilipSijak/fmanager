import React from "react";
import PlayerMenu from "../Components/PlayerMenu";
import {Outlet} from "react-router-dom";

const Player: React.FC = () => {
    return (
        <>
            <div>Player page</div>
            <PlayerMenu />
            <Outlet />
        </>

    );
}

export default Player;
