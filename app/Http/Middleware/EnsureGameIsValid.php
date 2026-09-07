<?php

namespace App\Http\Middleware;

use App\Support\GameContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGameIsValid
{
    public function __construct(
        private readonly GameContext $gameContext
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->gameContext->hasInstanceId()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Select a game first.'], 422)
                : redirect()->route('setup-game');
        }

        return $next($request);
    }
}
