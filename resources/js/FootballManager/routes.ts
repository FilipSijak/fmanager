import {HomeScreenRoutes} from "./Modules/HomeScreen/routes";

export type AppRoute = {
    path: string,
    Component: any
}

let appRoutes: AppRoute[] = [];

appRoutes = appRoutes.concat(
    HomeScreenRoutes
);

export const routes = appRoutes;
