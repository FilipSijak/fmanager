import type { DragEvent } from 'react';

/** Drag payload type for a player dragged anywhere on the live match screen. */
const LIVE_PLAYER_DRAG_TYPE = 'application/x-live-match-player';

export type DraggedPlayer = {
    playerId: number;
    /** Where the drag started: a player on the pitch or a substitute on the bench. */
    from: 'pitch' | 'bench';
    /** The formation slot the player was dragged from, on the tactics pitch. */
    slot?: string;
};

export function startPlayerDrag(event: DragEvent, player: DraggedPlayer): void {
    event.dataTransfer.setData(LIVE_PLAYER_DRAG_TYPE, JSON.stringify(player));
    event.dataTransfer.effectAllowed = 'move';
}

/** Lets an element accept dropped players; returns props to spread onto it. */
export function playerDropTarget(
    onDropPlayer: (player: DraggedPlayer) => void,
) {
    return {
        onDragOver: (event: DragEvent) => {
            if (event.dataTransfer.types.includes(LIVE_PLAYER_DRAG_TYPE)) {
                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
            }
        },
        onDrop: (event: DragEvent) => {
            const payload = event.dataTransfer.getData(LIVE_PLAYER_DRAG_TYPE);

            if (payload) {
                event.preventDefault();
                onDropPlayer(JSON.parse(payload) as DraggedPlayer);
            }
        },
    };
}
