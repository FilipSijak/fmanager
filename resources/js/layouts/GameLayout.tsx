import type { ReactNode } from 'react';
import Sidebar from '@/components/game/Sidebar';

export default function GameLayout({
    active,
    children,
}: {
    active?: string;
    children: ReactNode;
}) {
    return (
        <div className="hidden min-[1200px]:flex min-h-screen bg-[#000018]">
            <Sidebar active={active} />
            <div className="flex min-w-0 flex-1 flex-col">{children}</div>
        </div>
    );
}
