<?php

namespace App\Services\CompetitionService\Competitions;

class KnockoutSummary
{
    /**
     * @var array
     */
    private $firstGroup;
    /**
     * @var array
     */
    private $secondGroup;
    /**
     * @var int
     */
    private $firstPlacedTeam;
    /**
     * @var int
     */
    private $secondPlacedTeam;
    /**
     * @var int
     */
    private $thirdPlacedTeam;

    public function setFirstGroup(array $firstGroup)
    {
        $this->firstGroup = $firstGroup;
    }

    public function getFirstGroup()
    {
        return $this->firstGroup;
    }

    public function setSecondGroup(array $secondGroup)
    {
        $this->secondGroup = $secondGroup;
    }

    public function getSecondGroup()
    {
        return $this->secondGroup;
    }

    public function setFirstPlacedTeam(int $teamId)
    {
        $this->firstPlacedTeam = $teamId;
    }

    public function setSecondPlacedTeam(int $teamId)
    {
        $this->secondPlacedTeam = $teamId;
    }

    public function setThirdPlacedTeam(int $teamId)
    {
        $this->thirdPlacedTeam = $teamId;
    }
}
