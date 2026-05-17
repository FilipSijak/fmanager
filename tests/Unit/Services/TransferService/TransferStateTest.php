<?php

namespace Tests\Unit\Services\TransferService;

use App\Exceptions\InvalidTransferTransition;
use App\Models\Transfer;
use App\Services\TransferService\TransferState;
use App\Services\TransferService\TransferStatusTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransferStateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_moves_a_transfer_through_an_allowed_transition(): void
    {
        $transfer = Transfer::factory()->create([
            'transfer_status' => TransferStatusTypes::WAITING_PLAYER->value,
        ]);

        (new TransferState())->transitionTo($transfer, TransferStatusTypes::WAITING_PAPERWORK);

        $this->assertSame(
            TransferStatusTypes::WAITING_PAPERWORK->value,
            $transfer->fresh()->transfer_status
        );
    }

    #[Test]
    public function it_rejects_an_invalid_transition(): void
    {
        $transfer = Transfer::factory()->create([
            'transfer_status' => TransferStatusTypes::WAITING_TARGET_CLUB->value,
        ]);

        $this->expectException(InvalidTransferTransition::class);

        (new TransferState())->transitionTo($transfer, TransferStatusTypes::TRANSFER_COMPLETED);
    }
}
