<?php

namespace Tests\Integration\Services\ClubService;

use App\Models\Account;
use App\Models\Club;
use App\Models\Transfer;
use App\Services\ClubService\FinancialAnalysis\ClubFinancialAnalysis;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class FinancialAnalysisTest extends TestCase
{
    use DatabaseMigrations;

    /** @test  */
    public function it_can_check_transfer_offer_is_acceptable()
    {
        (new DatabaseSeeder())->run();

        Club::factory()->create();
        Account::factory()->create();

        $financialAnalysis = new ClubFinancialAnalysis();
        $transfer = Transfer::factory()->create();
        $acceptableTransfer = $financialAnalysis->isFinanciallyAcceptableTransfer($transfer);

        $this->assertTrue($acceptableTransfer);
    }
}
