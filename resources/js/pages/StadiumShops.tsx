import { Head } from '@inertiajs/react';
import RetroPriceListCard, {
    type TradingItem,
} from '@/components/game/RetroPriceListCard';
import StadiumSubPageHeader from '@/components/game/StadiumSubPageHeader';
import GameLayout from '@/layouts/GameLayout';

const shopItems: TradingItem[] = [
    {
        id: 'vinyl-record',
        name: 'Vinyl Record',
        description: 'Classic match anthems, from Vinyl Records',
        price: 15.0,
    },
    {
        id: 'club-scarf',
        name: 'Club Scarf',
        description: 'Official matchday scarf, from Sports Gear',
        price: 12.0,
    },
    {
        id: 'football-boots',
        name: 'Football Boots',
        description: 'Pro-level boots, from Sports Gear',
        price: 45.0,
    },
    {
        id: 'fruit-box',
        name: 'Fresh Fruit Box',
        description: 'Mixed seasonal fruit, from Green Grocer',
        price: 6.5,
    },
    {
        id: 'haircut',
        name: 'Matchday Haircut',
        description: 'Fresh trim, from City Cuts Barbershop',
        price: 18.0,
    },
    {
        id: 'programme',
        name: 'Matchday Programme',
        description: 'Official programme, from Books',
        price: 5.0,
    },
];

export default function StadiumShops() {
    return (
        <GameLayout active="Nations & Clubs">
            <Head title="AC Milan - Shops" />
            <main className="relative flex min-h-screen flex-1 flex-col items-center bg-[#1a1410] p-6 text-white sm:p-10">
                <StadiumSubPageHeader />
                <RetroPriceListCard
                    eyebrow="Fan Village"
                    title="Shop Price List"
                    items={shopItems}
                />
            </main>
        </GameLayout>
    );
}
