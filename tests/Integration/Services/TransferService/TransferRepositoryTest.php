<?php

namespace Tests\Integration\Services\TransferService;

use App\Models\Club;
use App\Models\Instance;
use App\Models\Player;
use App\Repositories\TransferRepository;
use App\Services\TransferService\Data\FreeTransferOfferData;
use App\Services\TransferService\Data\TransferOfferData;
use App\Services\TransferService\TransferStatusTypes;
use App\Services\TransferService\TransferType;
use App\Support\GameContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransferRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private TransferRepository $repository;

    private Club $buyingClub;

    private Club $sellingClub;

    private Player $player;

    protected function setUp(): void
    {
        parent::setUp();

        Instance::factory()->create(['id' => 1, 'season_id' => 1, 'instance_date' => '2026-08-28']);
        $this->buyingClub = Club::factory()->create(['id' => 1, 'instance_id' => 1]);
        $this->sellingClub = Club::factory()->create(['id' => 2, 'instance_id' => 1]);
        $this->player = Player::factory()->create(['instance_id' => 1, 'club_id' => 2]);
        app(GameContext::class)->set(1, 1, '2026-08-28');
        $this->repository = app(TransferRepository::class);
    }

    #[Test]
    public function it_stores_a_transfer_and_financial_details_as_one_aggregate(): void
    {
        $transfer = $this->repository->storeTransfer(new TransferOfferData(
            sourceClubId: 1,
            targetClubId: 2,
            playerId: $this->player->id,
            transferType: TransferType::PERMANENT_TRANSFER,
            amount: 2_000,
            installments: 12,
        ));

        $this->assertSame('2026-08-28', $transfer->offer_date);
        $this->assertSame(TransferStatusTypes::WAITING_TARGET_CLUB->value, $transfer->transfer_status);
        $this->assertDatabaseHas('transfer_financial_details', [
            'transfer_id' => $transfer->id,
            'amount' => 2_000,
            'installments' => 12,
        ]);
    }

    #[Test]
    public function it_stores_a_free_transfer_with_zero_defaults_for_optional_bonuses(): void
    {
        $this->player->forceFill(['club_id' => null, 'contract_id' => null])->save();

        $transfer = $this->repository->storeFreeTransfer(new FreeTransferOfferData(
            sourceClubId: 1,
            playerId: $this->player->id,
            salary: 500,
        ));

        $this->assertDatabaseHas('transfer_contract_offers', [
            'transfer_id' => $transfer->id,
            'salary' => 500,
            'appearance' => 0,
            'assist' => 0,
            'goal' => 0,
        ]);
    }

    #[Test]
    public function it_rejects_entities_from_another_instance(): void
    {
        Instance::factory()->create(['id' => 2]);
        $otherClub = Club::factory()->create(['instance_id' => 2]);

        $this->expectException(InvalidArgumentException::class);

        $this->repository->storeTransfer(new TransferOfferData(
            sourceClubId: 1,
            targetClubId: $otherClub->id,
            playerId: $this->player->id,
            transferType: TransferType::PERMANENT_TRANSFER,
            amount: 2_000,
            installments: 0,
        ));
    }
}
