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
            ?? ($request->hasSession() ? $request->session()->get('active_instance_hash') : null);

        if ($instanceHash !== null) {
            $instance = $request->user()->instances()
                ->where('instance_hash', $instanceHash)
                ->firstOrFail();
            $this->gameContext->set($instance->id, $instance->season_id, (string) $instance->instance_date);
        }

        return $next($request);
    }
}
