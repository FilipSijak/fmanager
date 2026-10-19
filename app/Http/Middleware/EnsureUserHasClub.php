<?php

namespace App\Http\Middleware;

use App\Models\Instance;
use App\Support\GameContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks club management pages and APIs while the user's manager is without a club.
 */
class EnsureUserHasClub
{
    public function __construct(private readonly GameContext $gameContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->gameContext->hasInstanceId()) {
            return $next($request);
        }

        $hasClub = Instance::query()->whereKey($this->gameContext->instanceId())->whereNotNull('club_id')->exists();

        if (! $hasClub) {
            return $request->expectsJson()
                ? response()->json(['message' => 'You are not managing a club.'], 403)
                : redirect()->route('start');
        }

        return $next($request);
    }
}
