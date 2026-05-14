<?php

namespace App\Support;

use RuntimeException;

class GameContext
{
    private ?int $instanceId = null;
    private ?int $seasonId = null;

    public function set(?int $instanceId, ?int $seasonId): void
    {
        $this->instanceId = $instanceId;
        $this->seasonId = $seasonId;
    }

    public function setInstanceId(?int $instanceId): void
    {
        $this->instanceId = $instanceId;
    }

    public function setSeasonId(?int $seasonId): void
    {
        $this->seasonId = $seasonId;
    }

    public function instanceId(): int
    {
        if ($this->instanceId === null) {
            throw new RuntimeException('Game context instance id has not been set.');
        }

        return $this->instanceId;
    }

    public function seasonId(): int
    {
        if ($this->seasonId === null) {
            throw new RuntimeException('Game context season id has not been set.');
        }

        return $this->seasonId;
    }

    public function hasInstanceId(): bool
    {
        return $this->instanceId !== null;
    }

    public function hasSeasonId(): bool
    {
        return $this->seasonId !== null;
    }
}
