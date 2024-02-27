import React from 'react';
import {Sidebar, Menu, MenuItem, SubMenu} from 'react-pro-sidebar'
import {Link} from "react-router-dom";

export const LayoutSidebar: React.FC = () => {
    return (
        <Sidebar
        >
            <Menu>
                <MenuItem component={<Link to="/" />}> Continue </MenuItem>
                <SubMenu label="Club">
                    <MenuItem component={<Link to="/squad" />}> Squad </MenuItem>
                    <MenuItem component={<Link to="/tactics" />}> Tactics </MenuItem>
                    <MenuItem component={<Link to="/training" />}> Training </MenuItem>
                    <MenuItem component={<Link to="/finances" />}> Finances </MenuItem>
                </SubMenu>
                <SubMenu label="Competitions">
                    <MenuItem component={<Link to="/competition/1" />}> League table </MenuItem>
                    <MenuItem component={<Link to="/competition/2" />}> Europe </MenuItem>
                </SubMenu>
                <MenuItem> Find </MenuItem>
            </Menu>
        </Sidebar>
    );
}
