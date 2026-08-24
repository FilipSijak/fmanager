<?php

namespace App\Support;

use RuntimeException;

class GameContext
{
    private ?int $instanceId = null;
    private ?int $seasonId = null;
    private ?string $instanceDate = null;

    public function set(?int $instanceId, ?int $seasonId, ?string $instanceDate = null): void
    {
        $this->instanceId = $instanceId;
        $this->seasonId = $seasonId;
        $this->instanceDate = $instanceDate;
    }

    public function setInstanceId(?int $instanceId): void
    {
        $this->instanceId = $instanceId;
        $this->instanceDate = null;
    }

    public function setInstanceDate(?string $instanceDate): void
    {
        $this->instanceDate = $instanceDate;
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

    public function instanceDate(): string
    {
        if ($this->instanceDate === null) {
            throw new RuntimeException('Game context instance date has not been set.');
        }

        return $this->instanceDate;
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

    public function hasInstanceDate(): bool
    {
        return $this->instanceDate !== null;
    }

    public function hasSeasonId(): bool
    {
        return $this->seasonId !== null;
    }
}
