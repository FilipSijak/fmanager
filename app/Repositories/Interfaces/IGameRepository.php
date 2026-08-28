<?php

namespace App\Repositories\Interfaces;

interface IGameRepository
{
    /** @return array<string, mixed>|null */
    public function getFullGameData(int $gameId): ?array;
}
