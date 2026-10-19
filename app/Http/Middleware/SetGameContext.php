<?php

namespace App\Http\Middleware;

use App\Support\GameContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetGameContext
{
    public function __construct(private readonly GameContext $gameContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->gameContext->set(null, null);
        $instanceHash = $request->header('instanceHash')
            ?? ($request->hasSession() ? $request->session()->get('active_instance_hash') : null)
            ?? $this->defaultInstanceHash($request);

        if ($instanceHash !== null) {
            $instance = $request->user()->instances()
                ->where('instance_hash', $instanceHash)
                ->firstOrFail();
            if ($instance->club_id === null) {
                return $request->expectsJson()
                    ? response()->json(['message' => 'Choose a club to finish setting up this game.'], 422)
                    : redirect()->route('setup-game', ['instance_id' => $instance->id]);
            }
            $this->gameContext->set($instance->id, $instance->season_id, (string) $instance->instance_date);
        }

        return $next($request);
    }

    /**
     * Resume the user's only game when the session has no active game; users with several games pick one themselves.
     */
    private function defaultInstanceHash(Request $request): ?string
    {
        if ($request->user() === null || ! $request->hasSession() || $request->user()->instances()->count() !== 1) {
            return null;
        }

        $instanceHash = $request->user()->managers()
            ->join('instances', 'instances.id', '=', 'managers.instance_id')
            ->whereNotNull('instances.club_id')
            ->value('instances.instance_hash');

        if ($instanceHash !== null) {
            $request->session()->put('active_instance_hash', $instanceHash);
        }

        return $instanceHash;
    }
}
