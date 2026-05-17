<?php

namespace Tests\Integration\Services\TransferService;

use App\Models\Instance;
use App\Models\Account;
use App\Models\Club;
use App\Models\Transfer;
use App\Repositories\TransferRepository;
use App\Services\TransferService\TransferStatusTypes;
use App\Services\TransferService\TransferTypes;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransferRepositoryTransferPlayerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_moves_a_transfer_outside_the_window_to_waiting_transfer_window(): void
    {
        Instance::factory()->create([
            'id' => 1,
            'instance_date' => '2024-03-01',
        ]);

        $transfer = Transfer::factory()->create([
            'season_id' => 1,
            'transfer_status' => TransferStatusTypes::MOVE_PLAYER->value,
            'transfer_type' => TransferTypes::PERMANENT_TRANSFER,
        ]);

        $this->transferRepository()->transferPlayerToNewClub($transfer);

        $transfer->refresh();

        $this->assertSame(TransferStatusTypes::WAITING_TRANSFER_WINDOW->value, $transfer->transfer_status);
        $this->assertSame('2024-07-01', $transfer->transfer_date);
    }

    #[Test]
    public function it_uses_the_transfers_instance_date_when_checking_the_transfer_window(): void
    {
        Instance::factory()->create([
            'id' => 1,
            'instance_date' => '2024-07-01',
        ]);
        Instance::factory()->create([
            'id' => 2,
            'instance_date' => '2024-03-01',
        ]);

        $transfer = Transfer::factory()->create([
            'instance_id' => 2,
            'season_id' => 1,
            'transfer_status' => TransferStatusTypes::MOVE_PLAYER->value,
            'transfer_type' => TransferTypes::PERMANENT_TRANSFER,
        ]);

        $transferRepository = $this->transferRepository();
        $transferRepository->setInstanceId(1);
        $transferRepository->transferPlayerToNewClub($transfer);

        $transfer->refresh();

        $this->assertSame(TransferStatusTypes::WAITING_TRANSFER_WINDOW->value, $transfer->transfer_status);
        $this->assertSame('2024-07-01', $transfer->transfer_date);
    }

    #[Test]
    public function it_fails_explicitly_when_a_transfer_contract_offer_is_missing(): void
    {
        Instance::factory()->create([
            'id' => 1,
            'instance_date' => '2024-07-01',
        ]);
        $sourceClub = Club::factory()->create(['id' => 10]);
        Account::factory()->create([
            'club_id' => $sourceClub->id,
            'transfer_budget' => 10000,
        ]);

        $transfer = Transfer::factory()->create([
            'source_club_id' => $sourceClub->id,
            'season_id' => 1,
            'transfer_status' => TransferStatusTypes::MOVE_PLAYER->value,
            'transfer_type' => TransferTypes::FREE_TRANSFER,
        ]);

        $this->expectException(ModelNotFoundException::class);

        $this->transferRepository()->transferPlayerToNewClub($transfer);
    }

    private function transferRepository(): TransferRepository
    {
        $transferRepository = app()->make(TransferRepository::class);
        $transferRepository->setSeasonId(1);
        $transferRepository->setInstanceId(1);

        return $transferRepository;
    }
}
