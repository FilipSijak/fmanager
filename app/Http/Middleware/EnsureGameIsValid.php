<?php

namespace App\Http\Middleware;

use App\Support\GameContext;
use Closure;
use Illuminate\Http\Request;

class EnsureGameIsValid
{
    public function __construct(
        private readonly GameContext $gameContext
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if (!$this->gameContext->hasInstanceId()) {
            return response()->json('Your account is inactive');
        }

        return $next($request);
    }
}
