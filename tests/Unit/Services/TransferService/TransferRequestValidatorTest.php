<?php

namespace Tests\Unit\Services\TransferService;

use App\Services\TransferService\TransferRequest\TransferRequestValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransferRequestValidatorTest extends TestCase
{
    #[Test]
    #[DataProvider('loanTransferRequestData')]
    public function itValidatesLoanTransferRequest($expected, $failed)
    {
        $loanValidationSuccess = (new TransferRequestValidator())->validateLoanTransferRequest($expected);
        $loanValidationFail = (new TransferRequestValidator())->validateLoanTransferRequest($failed);

        $this->assertEquals([], $loanValidationSuccess);
        $this->assertNotEmpty($loanValidationFail);
    }

    #[Test]
    #[DataProvider('permanentTransferRequestData')]
    public function itValidatesFreeTransferRequest($expected, $failed)
    {
        $freeTransferValidationSuccess = (new TransferRequestValidator())->validateFreeTransferRequest($expected);
        $freeTransferValidationFail = (new TransferRequestValidator())->validateFreeTransferRequest($failed);

        $this->assertEquals([], $freeTransferValidationSuccess);
        $this->assertNotEmpty($freeTransferValidationFail);
    }

    #[Test]
    #[DataProvider('loanTransferRequestData')]
    public function itValidatesPermanentTransferRequest($expected, $failed)
    {
        $permanentTransferValidationSuccess = (new TransferRequestValidator())->validatePermanentTransferRequest($expected);
        $permanentTransferValidationFail = (new TransferRequestValidator())->validatePermanentTransferRequest($failed);

        $this->assertEquals([], $permanentTransferValidationSuccess);
        $this->assertNotEmpty($permanentTransferValidationFail);
    }

    public static function loanTransferRequestData(): array
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

    public static function freeTransferRequestData(): array
    {
        return [
            'Free transfer required fields' => [
                'expected' => [
                    'source_club_id' => '1',
                    'player_id' => '1',
                    'season_id' => '1',
                    'offer_date' => '1',
                ],
                'failed' => [],
            ],
        ];
    }

    public static function permanentTransferRequestData(): array
    {
        return [
            'Free transfer required fields' => [
                'expected' => [
                    'source_club_id' => '1',
                    'target_club_id' => '1',
                    'player_id' => '1',
                    'season_id' => '1',
                    'offer_date' => '1',
                    'amount' => '1',
                ],
                'failed' => [
                    'offer_date' => '1',
                ],
            ],
        ];
    }
}
