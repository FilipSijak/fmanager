import type { Formation } from '../../tactics/types';
import type { LiveMatchData, SlotAssignments, TacticsDraft } from './types';

/**
 * Moves the placed players into another formation: each player keeps their position where the new formation has
 * it, and the rest fill the remaining places in order. Places nobody fills stay empty.
 */
export function reassignToFormation(
    from: { position: string; playerId: number | null }[],
    formation: Formation,
): SlotAssignments {
    const remaining = from.filter((place) => place.playerId !== null);
    const assignments: SlotAssignments = {};

    for (const slot of formation.slots) {
        const samePosition = remaining.findIndex(
            (place) => place.position === slot.position,
        );

        if (samePosition !== -1) {
            assignments[slot.slot] = remaining[samePosition].playerId;
            remaining.splice(samePosition, 1);
        }
    }

    for (const slot of formation.slots) {
        if (!(slot.slot in assignments)) {
            assignments[slot.slot] = remaining.shift()?.playerId ?? null;
        }
    }

    return assignments;
}

/** The user's current formation, mentality and positions, as the tactics tab starts editing them. */
export function draftFromMatch(
    match: LiveMatchData,
    formations: Formation[],
): TacticsDraft | null {
    const team = match[match.user_side];
    const current = formations.find(
        (formation) => formation.id === team.formation_id,
    );
    const formation = current ?? formations[0];

    if (!formation) {
        return null;
    }

    const assignments = current
        ? Object.fromEntries(
              formation.slots.map((slot, index) => [
                  slot.slot,
                  team.on_pitch[index]?.player_id ?? null,
              ]),
          )
        : reassignToFormation(
              team.on_pitch.map((player) => ({
                  position: player.position,
                  playerId: player.player_id,
              })),
              formation,
          );

    return { formationId: formation.id, mentality: team.mentality, assignments };
}

export function shortName(name: string | null): string {
    if (!name) {
        return '';
    }

    const parts = name.trim().split(/\s+/);

    return parts[parts.length - 1];
}
