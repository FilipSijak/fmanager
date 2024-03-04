import {AppRoute} from "../../routes";
import Squad from "./Pages/Squad";
import Tactics from "./Pages/Tactics";

export const ClubRoutes: AppRoute[] = [
    {
        path: '/squad',
        Component: Squad,
        childRoutes: []
    },
    {
        path: '/tactics',
        Component: Tactics,
        childRoutes: []
    }
];
