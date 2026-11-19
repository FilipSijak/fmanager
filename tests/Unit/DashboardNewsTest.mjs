import assert from 'node:assert/strict';
import { test } from 'node:test';
import { getNewsFeed } from '../../resources/js/pages/dashboard-news.ts';

const news = [
    { id: 1, type: 'transfer', title: 'Transfer offer' },
    { id: 2, type: 'injury', title: 'Player injured' },
    { id: 3, type: 'match', title: 'Match result' },
    { id: 4, type: 'injury', title: 'Player recovered' },
    { id: 5, type: 'contract', title: 'Contract signed' },
    { id: 6, type: 'suspension', title: 'Player suspended' },
    { id: 7, type: 'sponsorship', title: 'Sponsor payment' },
    { id: 8, type: 'youth_intake', title: 'Academy graduates' },
    { id: 9, type: 'job', title: 'Job application' },
];

for (const [tab, expectedIds] of [
    ['All', [1, 2, 3, 4, 5, 6, 7, 8, 9]],
    ['Messages', [1, 5, 7, 8, 9]],
    ['Competitions', [3]],
    ['Injuries and Bans', [2, 4, 6]],
]) {
    test(`${tab} shows the matching news in feed order`, () => {
        const { items } = getNewsFeed(news, tab, null);

        assert.deepEqual(items.map((item) => item.id), expectedIds);
    });
}

test('a selection outside the active filter falls back to its first message', () => {
    const { selected } = getNewsFeed(news, 'Injuries and Bans', 1);

    assert.equal(selected?.id, 2);
});

test('the selected message stays selected when the feed order changes', () => {
    const { selected } = getNewsFeed([...news].reverse(), 'Injuries and Bans', 4);

    assert.equal(selected?.id, 4);
});

test('empty categories clear the selected article', () => {
    const { items, selected } = getNewsFeed([news[0]], 'Injuries and Bans', 1);

    assert.deepEqual(items, []);
    assert.equal(selected, undefined);
});

test('a removed message falls back to the first remaining message', () => {
    const { selected } = getNewsFeed(news.filter((item) => item.id !== 4), 'Injuries and Bans', 4);

    assert.equal(selected?.id, 2);
});
