import {HomeScreenRoutes} from "./Modules/HomeScreen/routes";
import {ClubRoutes} from "./Modules/Club/routes";
import {PlayerRoutes} from "./Modules/Player/routes";

export type AppRoute = {
    path: string,
    Component: any,
    name?: string
}

let appRoutes: AppRoute[] = [];

appRoutes = appRoutes.concat(
    HomeScreenRoutes,
    ClubRoutes,
    PlayerRoutes
);

export const routes = appRoutes;
