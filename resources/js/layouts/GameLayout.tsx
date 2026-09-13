import type { ReactNode } from 'react';
import Sidebar from '@/components/game/Sidebar';
import TopMenu from '@/components/game/TopMenu';

export default function GameLayout({
    active,
    children,
}: {
    active?: string;
    children: ReactNode;
}) {
    return (
        <div className="hidden min-h-screen bg-[#000018] min-[1200px]:flex">
            <Sidebar active={active} />
            <div className="relative flex min-w-0 flex-1 flex-col">
                <TopMenu />
                {children}
            </div>
        </div>
    );
}
