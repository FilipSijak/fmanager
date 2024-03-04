import React from 'react';
import {Provider} from "react-redux";
import {BrowserRouter, Navigate, Route, Routes} from "react-router-dom";
import {AppRoute, routes} from "./routes";
import Home from "./Modules/HomeScreen/Pages";
import {store} from "./store";
import {LayoutHeader} from "./UI/LayoutHeader";
import {LayoutContainer} from "./UI/LayoutContainer";
import {LayoutSidebar} from "./UI/LayoutSidebar";

export const App: React.FC = () => {
    return (
        <React.StrictMode>
            <Provider store={store}>
                <BrowserRouter>
                    <LayoutContainer>
                        <Routes>
                            {routes.map(
                                ({ path, Component, childRoutes }: AppRoute) => (
                                    childRoutes ?
                                    <Route path={path} element={<Component />} >
                                        {childRoutes.map(({ path, Component }: AppRoute) => {
                                            return <Route path={path} element={<Component />} />
                                        })}
                                    </Route> :
                                    <Route path={path} element={<Component />} />
                                )
                            )}
                        </Routes>
                    </LayoutContainer>
                </BrowserRouter>
            </Provider>
        </React.StrictMode>
    );
}
