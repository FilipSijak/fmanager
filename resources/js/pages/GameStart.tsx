import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import {
    select,
    startNewGame,
} from '@/actions/App/Http/Controllers/InstanceController';
import api from '@/api';
import GameLayout from '@/layouts/GameLayout';
import { start } from '@/routes';

const menuRows: [string, string][] = [
    ['Start New Game', 'Quick Start Game'],
    ['Restore Saved Game', 'Delete Saved Game'],
    ['Network Play', 'Game Settings'],
    ['Hall Of Fame', 'Game Credits'],
];

type SavedGame = { id: number; club_name: string | null; date: string };

export default function GameStart({ instances }: { instances: SavedGame[] }) {
    const [selecting, setSelecting] = useState<number | null>(null);
    const [processing, setProcessing] = useState(false);
    const [created, setCreated] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const starting = useRef(false);
    const setupDialog = useRef<HTMLDialogElement>(null);

    useEffect(() => {
        if (processing) {
            setupDialog.current?.showModal();
        } else {
            setupDialog.current?.close();
        }
    }, [processing]);

    async function createGame() {
        if (starting.current) return;
        starting.current = true;
        setProcessing(true);
        setError(null);
        setCreated(false);

        try {
            await api.post(startNewGame.url());
            setCreated(true);
            router.visit(start.url());
        } catch {
            setError('Unable to start a new game. Please try again.');
            starting.current = false;
            setProcessing(false);
        }
    }

    return (
        <GameLayout active="Game Options">
            <Head title="Setup Game" />

            <dialog
                ref={setupDialog}
                aria-labelledby="setup-dialog-title"
                aria-describedby="setup-dialog-description"
                onCancel={(event) => event.preventDefault()}
                className="fixed inset-0 m-auto w-full max-w-lg rounded border border-[#3355dd] bg-[#101358] p-6 text-white backdrop:bg-black/70"
            >
                <div className="flex flex-col gap-4">
                    <h2
                        id="setup-dialog-title"
                        className="text-xl font-bold text-[#f5f000]"
                    >
                        {created
                            ? 'Your game is ready'
                            : 'Setting up your game'}
                    </h2>
                    <p id="setup-dialog-description" role="status">
                        {created
                            ? 'Your game has been set up successfully. Opening Dashboard…'
                            : 'Your game is currently being set up. You will be taken to Dashboard when it is finished. Please keep this page open.'}
                    </p>
                </div>
            </dialog>

            <header className="border-b border-black bg-[#fc0000] px-6 py-4">
                <h1 className="text-center text-2xl font-bold text-white">
                    Championship Manager 2001/02
                </h1>
            </header>

            <div className="flex flex-1 flex-col items-center gap-6 bg-gradient-to-b from-[#1a2233] to-[#05070c] px-10 py-8">
                <h2 className="text-xl font-bold text-[#f5f000]">Setup Game</h2>

                {error && <p role="alert">{error}</p>}
                <div className="grid w-full max-w-2xl grid-cols-2 gap-0 overflow-hidden rounded border border-[#3355dd]">
                    {menuRows.map((row) =>
                        row.map((label) => (
                            <button
                                key={label}
                                type="button"
                                disabled={
                                    processing ||
                                    selecting !== null ||
                                    ![
                                        'Start New Game',
                                        'Restore Saved Game',
                                    ].includes(label)
                                }
                                onClick={
                                    label === 'Start New Game'
                                        ? createGame
                                        : label === 'Restore Saved Game'
                                          ? () =>
                                                document
                                                    .getElementById('my-games')
                                                    ?.scrollIntoView({
                                                        behavior: 'smooth',
                                                    })
                                          : undefined
                                }
                                className="border border-[#3355dd]/60 bg-black/30 px-6 py-5 text-base font-semibold text-white hover:bg-white/10"
                            >
                                {label}
                            </button>
                        )),
                    )}
                </div>

                <section
                    id="my-games"
                    className="flex w-full max-w-2xl flex-col gap-3"
                    aria-labelledby="my-games-title"
                >
                    <h2
                        id="my-games-title"
                        className="text-xl font-bold text-[#f5f000]"
                    >
                        My Games
                    </h2>
                    {instances.length === 0 && (
                        <p className="text-white">
                            You have no saved games yet. Start a new game above.
                        </p>
                    )}
                    {instances.map((instance) => (
                        <div
                            key={instance.id}
                            className="flex items-center justify-between gap-4 rounded border border-[#3355dd] bg-black/30 p-4 text-white"
                        >
                            <p>
                                {instance.club_name ?? 'Game'} — {instance.date}{' '}
                                (#{instance.id})
                            </p>
                            <button
                                type="button"
                                disabled={processing || selecting !== null}
                                onClick={() => {
                                    setSelecting(instance.id);
                                    setError(null);
                                    router.post(
                                        select.url(instance.id),
                                        {},
                                        {
                                            onError: () =>
                                                setError(
                                                    'Unable to open this game. Please try again.',
                                                ),
                                            onFinish: () => setSelecting(null),
                                        },
                                    );
                                }}
                                className="rounded border border-[#3355dd] px-4 py-2 font-semibold text-[#f5f000] hover:bg-white/10 disabled:opacity-50"
                            >
                                {selecting === instance.id
                                    ? 'Opening…'
                                    : 'Resume'}
                            </button>
                        </div>
                    ))}
                </section>

                <button
                    type="button"
                    className="w-full max-w-2xl border border-[#3355dd]/60 bg-black/30 px-6 py-5 text-base font-semibold text-white hover:bg-white/10"
                >
                    Web Sites
                </button>
            </div>

            <div className="flex bg-[#86888a]">
                <button
                    type="button"
                    disabled
                    className="flex-1 border-r border-slate-400 py-4 text-lg font-bold text-slate-500"
                >
                    Back
                </button>
                <button
                    type="button"
                    disabled
                    className="flex-1 py-4 text-lg font-bold text-slate-500"
                >
                    Next
                </button>
            </div>
        </GameLayout>
    );
}
