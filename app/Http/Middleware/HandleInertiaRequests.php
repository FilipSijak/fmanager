<?php

namespace App\Http\Middleware;

use App\Models\Instance;
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
     * @return array{date: string|null, has_club: bool}|null
     */
    private function gameState(): ?array
    {
        $gameContext = app(GameContext::class);

        if (! $gameContext->hasInstanceId()) {
            return null;
        }

        return [
            'date' => $gameContext->hasInstanceDate() ? $gameContext->instanceDate() : null,
            'has_club' => Instance::query()->whereKey($gameContext->instanceId())->whereNotNull('club_id')->exists(),
        ];
    }
}
