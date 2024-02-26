import React from 'react';
import {Props} from "focus-trap-react";
import {LayoutHeader} from "./LayoutHeader";
import {LayoutSidebar} from "./LayoutSidebar";

export const LayoutContainer: React.FC<Props> = ({ children }) => {
    return (
        <div className="container">
            <LayoutHeader />
            <div className="container-body">
                <LayoutSidebar />
                <div className="main-screen">
                    {children}
                </div>
            </div>

        </div>
    );
}
