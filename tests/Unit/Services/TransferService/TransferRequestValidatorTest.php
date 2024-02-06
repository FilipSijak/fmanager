<?php

namespace Tests\Unit\Services\TransferService;

use App\Services\TransferService\TransferRequest\TransferRequestValidator;
use Tests\TestCase;

class TransferRequestValidatorTest extends TestCase
{
    /**
     * @test
     * @dataProvider loanTransferRequestData
     */
    public function itValidatesLoanTransferRequest($expected, $failed)
    {

        $loanValidationSuccess = (new TransferRequestValidator())->validateLoanTransferRequest($expected);
        $loanValidationFail = (new TransferRequestValidator())->validateLoanTransferRequest($failed);

        $this->assertEquals([], $loanValidationSuccess);
        $this->assertNotEmpty($loanValidationFail);
    }

    public function loanTransferRequestData(): array
    {
        return [
            'Loan transfer required fields' => [
                'expected' => [
                    'source_club_id' => '1',
                    'target_club_id' => '1',
                    'player_id' => '1',
                    'season_id' => '1',
                    'offer_date' => '1',
                    'amount' => '1',
                    'loan_start' => '1',
                    'loan_end' => '1',
                ],
                'failed' => [
                    'season_id' => '1',
                    'offer_date' => '1',
                    'amount' => '1',
                    'loan_start' => '1',
                    'loan_end' => '1',
                ],
            ],
        ];
    }
}
