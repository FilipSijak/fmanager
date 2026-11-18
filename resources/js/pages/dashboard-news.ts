export const newsTabs = [
    'All',
    'Messages',
    'Competitions',
    'Injuries and Bans',
] as const;

export type NewsTab = (typeof newsTabs)[number];

export function getNewsFeed<T extends { id: number; type: string }>(
    news: T[],
    tab: NewsTab,
    selectedId: number | null,
): { items: T[]; selected: T | undefined } {
    const items = news.filter((item) => {
        const injuryOrBan =
            item.type === 'injury' || item.type === 'suspension';
        switch (tab) {
            case 'All':
                return true;
            case 'Messages':
                return item.type !== 'match' && !injuryOrBan;
            case 'Competitions':
                return item.type === 'match';
            case 'Injuries and Bans':
                return injuryOrBan;
            default:
                return false;
        }
    });

    return {
        items,
        selected: items.find((item) => item.id === selectedId) ?? items[0],
    };
}
