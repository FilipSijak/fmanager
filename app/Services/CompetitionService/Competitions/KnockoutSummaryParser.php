<?php

namespace App\Services\CompetitionService\Competitions;

class KnockoutSummaryParser
{
    /**
     * @var string
     */
    private $summarySchema;
    /**
     * @var KnockoutSummary
     */
    private $summaryModel;

    public function parseSchema(array $knockoutSummarySchema, KnockoutSummary $knockoutSummary)
    {
        $this->summarySchema = $knockoutSummarySchema;
        $this->summaryModel  = $knockoutSummary;

        $this->summaryModel->setFirstGroup($this->summarySchema["first_group"]);
        $this->summaryModel->setSecondGroup($this->summarySchema["second_group"]);
        /*$this->summaryModel->setFirstPlacedTeam($this->summarySchema["winner"]);
        $this->summaryModel->setSecondPlacedTeam($this->summarySchema["second_placed"]);
        $this->summaryModel->setThirdPlacedTeam($this->summarySchema["third_placed"]);*/
    }
}
