<?php

namespace App\DataModels;

class PlayerImportance
{
    private bool $key_player = false;
    private bool $best_in_position = false;
    private bool $acceptable_transfer = true;
    private bool $position_deficit = false;

    public function isKeyPlayer(): bool
    {
        return $this->key_player;
    }

    public function setKeyPlayer(bool $key_player): void
    {
        $this->key_player = $key_player;
    }

    public function isBestInPosition(): bool
    {
        return $this->best_in_position;
    }

    public function setBestInPosition(bool $best_in_position): void
    {
        $this->best_in_position = $best_in_position;
    }

    public function isAcceptableTransfer(): bool
    {
        return $this->acceptable_transfer;
    }

    public function setAcceptableTransfer(bool $acceptable_transfer): void
    {
        $this->acceptable_transfer = $acceptable_transfer;
    }

    public function isPositionDeficit(): bool
    {
        return $this->position_deficit;
    }

    public function setPositionDeficit(bool $position_deficit): void
    {
        $this->position_deficit = $position_deficit;
    }
}
