<?php

namespace App\Http\Middleware;

use App\Support\GameContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => ['user' => fn () => $request->user()?->only('id', 'name', 'email')],
            'game' => fn () => $this->gameState(),
        ];
    }

    /**
     * @return array{date: string}|null
     */
    private function gameState(): ?array
    {
        $gameContext = app(GameContext::class);

        return $gameContext->hasInstanceDate() ? ['date' => $gameContext->instanceDate()] : null;
    }
}
