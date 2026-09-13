import { Head } from '@inertiajs/react';
import RetroPriceListCard, {
    type TradingItem,
} from '@/components/game/RetroPriceListCard';
import StadiumSubPageHeader from '@/components/game/StadiumSubPageHeader';
import GameLayout from '@/layouts/GameLayout';

const barMenu: TradingItem[] = [
    {
        id: 'draft-lager',
        name: 'Draft Lager',
        description: 'Cold one, on tap',
        price: 4.5,
    },
    {
        id: 'craft-ipa',
        name: 'Craft IPA',
        description: 'Hoppy and bitter',
        price: 5.5,
    },
    {
        id: 'house-wine',
        name: 'House Wine',
        description: 'Red or white',
        price: 6.0,
    },
    {
        id: 'whiskey-shot',
        name: 'Whiskey Shot',
        description: 'Neat or on the rocks',
        price: 7.0,
    },
    {
        id: 'cocktail',
        name: "Manager's Cocktail",
        description: 'House special mix',
        price: 8.5,
    },
    {
        id: 'soda',
        name: 'Soft Drink',
        description: 'Cola, lemonade or soda water',
        price: 2.5,
    },
];

export default function StadiumBars() {
    return (
        <GameLayout active="Nations & Clubs">
            <Head title="AC Milan - Bars" />
            <main className="relative flex min-h-screen flex-1 flex-col items-center bg-[#1a1410] p-6 text-white sm:p-10">
                <StadiumSubPageHeader />
                <RetroPriceListCard
                    eyebrow="The Tavern"
                    title="Bar Menu"
                    items={barMenu}
                />
            </main>
        </GameLayout>
    );
}
