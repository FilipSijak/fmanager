import React from "react";
import {NavLink} from "react-router-dom";

const Squad: React.FC = () => {
    return (
        <>
            <div>Squad page</div>
            <ul>
                <li>
                    <NavLink to="/player">Player name</NavLink>
                </li>
            </ul>
        </>

    );
}

export default Squad;
