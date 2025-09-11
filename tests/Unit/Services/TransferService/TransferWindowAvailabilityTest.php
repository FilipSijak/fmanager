<?php

namespace Tests\Unit\Services\TransferService;

use App\Services\TransferService\TransferWindowConfig\TransferWindowAvailability;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransferWindowAvailabilityTest extends TestCase
{
    #[Test]
    public function testTransferWindowIsOpen()
    {
        $transferWindowAvailability = new TransferWindowAvailability();

        $isWindowOpen = $transferWindowAvailability->isTransferWindowOpen('2024-01-01');

        $this->assertTrue($isWindowOpen);

        $isWindowOpen = $transferWindowAvailability->isTransferWindowOpen('2024-02-01');

        $this->assertFalse($isWindowOpen);
    }
}
