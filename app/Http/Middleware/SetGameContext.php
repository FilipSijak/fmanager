<?php

namespace App\Http\Middleware;

use App\Models\Instance;
use App\Support\GameContext;
use Closure;
use Illuminate\Http\Request;

class SetGameContext
{
    public function __construct(
        private readonly GameContext $gameContext
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $instanceHash = $request->header('instanceHash');

        if ($instanceHash !== null) {
            $instance = Instance::where('instance_hash', $instanceHash)->first();

            if ($instance !== null) {
                $this->gameContext->set($instance->id, $instance->season_id);
            }
        }

        return $next($request);
    }
}
