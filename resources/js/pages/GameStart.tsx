import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { startNewGame } from '@/actions/App/Http/Controllers/InstanceController';
import api from '@/api';
import GameLayout from '@/layouts/GameLayout';
import { start } from '@/routes';

const menuRows: [string, string][] = [
    ['Start New Game', 'Quick Start Game'],
    ['Restore Saved Game', 'Delete Saved Game'],
    ['Network Play', 'Game Settings'],
    ['Hall Of Fame', 'Game Credits'],
];

export default function GameStart() {
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
            const response = await api.post<{
                data: { instance_hash: string };
            }>(startNewGame.url());
            window.localStorage.setItem(
                'instanceHash',
                response.data.data.instance_hash,
            );
            setCreated(true);
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
                            ? 'Your game has been set up successfully. You can now start playing.'
                            : 'Your game is currently being set up. You will be notified here when it is finished. Please keep this page open.'}
                    </p>
                    {created && (
                        <button
                            type="button"
                            onClick={() => router.visit(start.url())}
                            className="rounded border border-[#3355dd] bg-black/30 px-4 py-3 font-semibold text-[#f5f000] hover:bg-white/10"
                        >
                            Open Dashboard
                        </button>
                    )}
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
                                    processing || label !== 'Start New Game'
                                }
                                onClick={
                                    label === 'Start New Game'
                                        ? createGame
                                        : undefined
                                }
                                className="border border-[#3355dd]/60 bg-black/30 px-6 py-5 text-base font-semibold text-white hover:bg-white/10"
                            >
                                {label}
                            </button>
                        )),
                    )}
                </div>

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
