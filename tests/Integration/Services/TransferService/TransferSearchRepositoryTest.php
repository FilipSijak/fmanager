<?php

namespace Tests\Integration\Services\TransferService;

use App\Models\Account;
use App\Models\Club;
use App\Models\Instance;
use App\Models\Player;
use App\Models\PlayerContract;
use App\Models\Transfer;
use App\Models\TransferList;
use App\Repositories\TransferSearchRepository;
use App\Services\PersonService\PersonConfig\Player\PlayerFields;
use App\Services\SearchService\PlayerSearchQuery;
use App\Services\TransferService\TransferType;
use App\Support\GameContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransferSearchRepositoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_get_listed_player()
    {
        $position = 'CB';
        $buyingClub = Club::factory()->create(['id' => 2]);
        Account::factory()->create(['club_id' => $buyingClub->id, 'transfer_budget' => 1000000]);
        $sellingClub = Club::factory()->create(['id' => 1]);
        $listedPlayer = Player::factory()->create(
            [
                'club_id' => $sellingClub->id,
                'position' => $position,
                'potential' => 120,
                'instance_id' => 1,
                'value' => 50000,
            ]
        );

        // highest potential player in the same position from buying club
        Player::factory()->create(
            [
                'club_id' => $buyingClub->id,
                'position' => $position,
                'potential' => 100,
            ]
        );

        TransferList::factory()->create(
            ['player_id' => $listedPlayer->id, 'club_id' => $sellingClub->id, 'transfer_type' => TransferType::PERMANENT_TRANSFER->value]
        );

        $transferSearchRepository = app(TransferSearchRepository::class);
        app(GameContext::class)->setInstanceId(1);
        $clubBudget = $buyingClub->account->transfer_budget;

        $player = $transferSearchRepository->findListedPlayer($buyingClub, TransferType::PERMANENT_TRANSFER, $position, $clubBudget);

        $this->assertInstanceOf(Player::class, $player);
        $this->assertEquals($listedPlayer->id, $player->id);
    }

    #[Test]
    public function it_can_find_players_listed_for_loan()
    {
        $position = 'CB';
        $buyingClub = Club::factory()->create(['id' => 2]);
        $sellingClub = Club::factory()->create(['id' => 1]);

        $listedPlayer = Player::factory()->create(
            [
                'club_id' => $sellingClub->id,
                'position' => $position,
                'potential' => 120,
                'instance_id' => 1,
                'value' => 50000,
            ]
        );

        TransferList::factory()->create(
            ['player_id' => $listedPlayer->id, 'club_id' => $sellingClub->id, 'transfer_type' => TransferType::LOAN_TRANSFER->value]
        );

        Player::factory()->create(
            [
                'club_id' => $buyingClub->id,
                'position' => $position,
                'potential' => 100,
            ]
        );

        $transferSearchRepository = app(TransferSearchRepository::class);
        app(GameContext::class)->setInstanceId(1);

        $player = $transferSearchRepository->findListedLoanPlayer($buyingClub, $position);

        $this->assertInstanceOf(Player::class, $player);
        $this->assertEquals($listedPlayer->id, $player->id);
    }

    #[Test]
    public function it_can_find_luxury_player()
    {
        $position = 'CB';
        $buyingClub = Club::factory()->create(['id' => 2]);
        Account::factory()->create(['club_id' => $buyingClub->id, 'transfer_budget' => 60000000]);
        $sellingClub = Club::factory()->create(['id' => 1]);
        $luxuryPlayer = Player::factory()->create(
            [
                'club_id' => $sellingClub->id,
                'position' => $position,
                'potential' => 120,
                'instance_id' => 1,
                'value' => 500000,
            ]
        );

        // highest potential player in the same position from buying club
        Player::factory()->create(
            [
                'club_id' => $buyingClub->id,
                'position' => $position,
                'potential' => 100,
                'instance_id' => 1,
            ]
        );

        $transferSearchRepository = app(TransferSearchRepository::class);
        app(GameContext::class)->setInstanceId(1);
        $clubBudget = $buyingClub->account->transfer_budget;

        $player = $transferSearchRepository->findUpgradeTargetByPosition($buyingClub, $position, $clubBudget);

        $this->assertInstanceOf(Player::class, $player);
        $this->assertEquals($luxuryPlayer->id, $player->id);
    }

    #[Test]
    public function it_can_find_player_with_unprotected_contract()
    {
        $position = 'CB';
        $buyingClub = Club::factory()->create(['id' => 2, 'rank' => 10]);
        Account::factory()->create(['club_id' => $buyingClub->id, 'transfer_budget' => 1000000]);
        $sellingClub = Club::factory()->create(['id' => 1]);
        $unprotectedPlayer = Player::factory()->create(
            [
                'club_id' => $sellingClub->id,
                'position' => $position,
                'potential' => 120,
                'instance_id' => 1,
                'value' => 50000,
                'contract_id' => 1,
            ]
        );
        PlayerContract::factory()->create(['id' => 1, 'contract_end' => '2024-01-20']);
        Instance::factory()->create(['id' => 1, 'instance_date' => '2023-08-20']);
        $transferSearchRepository = app(TransferSearchRepository::class);
        app(GameContext::class)->set(1, null, '2023-08-20');
        $clubBudget = $buyingClub->account->transfer_budget;

        $player = $transferSearchRepository->findExpiringContractTarget($buyingClub, $position, $clubBudget);

        $this->assertInstanceOf(Player::class, $player);
        $this->assertEquals($unprotectedPlayer->id, $player->id);
    }

    #[Test]
    public function attribute_search_rejects_unknown_columns_and_returns_only_active_instance_players(): void
    {
        $repository = $this->repositoryWithContext('2024-07-01');
        $buyingClub = Club::factory()->create(['id' => 10, 'instance_id' => 1]);
        $sellingClub = Club::factory()->create(['id' => 11, 'instance_id' => 1]);
        $eligible = Player::factory()->create([
            'instance_id' => 1,
            'club_id' => $sellingClub->id,
            'pace' => 18,
            'potential' => 120,
        ]);
        Player::factory()->create([
            'instance_id' => 2,
            'club_id' => $sellingClub->id,
            'pace' => 20,
            'potential' => 200,
        ]);
        Player::factory()->create([
            'instance_id' => 1,
            'club_id' => $sellingClub->id,
            'pace' => 20,
            'potential' => 200,
            'is_retired' => true,
        ]);

        $players = $repository->findPlayersByAttributes($buyingClub, ['pace' => 18]);

        $this->assertSame([$eligible->id], $players->pluck('id')->all());

        $this->expectException(\InvalidArgumentException::class);
        $repository->findPlayersByAttributes($buyingClub, ['not_a_player_column' => 1]);
    }

    #[Test]
    public function position_search_handles_recent_old_and_other_club_offers(): void
    {
        $repository = $this->repositoryWithContext('2024-07-01');
        $buyingClub = Club::factory()->create(['id' => 20, 'instance_id' => 1, 'rank' => 10]);
        $otherBuyingClub = Club::factory()->create(['id' => 21, 'instance_id' => 1]);
        $sellingClub = Club::factory()->create(['id' => 22, 'instance_id' => 1]);
        $recent = Player::factory()->create([
            'instance_id' => 1, 'club_id' => $sellingClub->id, 'position' => 'CB', 'potential' => 150,
        ]);
        $old = Player::factory()->create([
            'instance_id' => 1, 'club_id' => $sellingClub->id, 'position' => 'CB', 'potential' => 140,
        ]);
        $otherClubOffer = Player::factory()->create([
            'instance_id' => 1, 'club_id' => $sellingClub->id, 'position' => 'CB', 'potential' => 130,
        ]);
        Player::factory()->create([
            'instance_id' => 2, 'club_id' => $sellingClub->id, 'position' => 'CB', 'potential' => 200,
        ]);

        Transfer::factory()->create([
            'instance_id' => 1, 'source_club_id' => $buyingClub->id,
            'player_id' => $recent->id, 'offer_date' => '2024-06-01',
        ]);
        Transfer::factory()->create([
            'instance_id' => 1, 'source_club_id' => $buyingClub->id,
            'player_id' => $old->id, 'offer_date' => '2022-06-30',
        ]);
        Transfer::factory()->create([
            'instance_id' => 1, 'source_club_id' => $otherBuyingClub->id,
            'player_id' => $otherClubOffer->id, 'offer_date' => '2024-06-01',
        ]);

        $players = $repository->findTransferTargetsByPosition($buyingClub, 'CB');

        $this->assertSame([$old->id, $otherClubOffer->id], $players->pluck('id')->all());
    }

    #[Test]
    public function listed_and_free_searches_respect_eligibility_and_position(): void
    {
        $repository = $this->repositoryWithContext();
        $buyingClub = Club::factory()->create(['id' => 30, 'instance_id' => 1, 'rank' => 1]);
        $sellingClub = Club::factory()->create(['id' => 31, 'instance_id' => 1]);
        $activeContract = PlayerContract::factory()->create([
            'contract_start' => '2023-01-01', 'contract_end' => '2026-01-01',
        ]);
        Player::factory()->create([
            'instance_id' => 1, 'club_id' => $buyingClub->id,
            'contract_id' => $activeContract->id, 'position' => 'CB', 'potential' => 100,
        ]);
        $listed = Player::factory()->create([
            'instance_id' => 1, 'club_id' => $sellingClub->id,
            'contract_id' => $activeContract->id, 'position' => 'CB',
            'potential' => 120, 'value' => 50000,
        ]);
        $wrongPosition = Player::factory()->create([
            'instance_id' => 1, 'club_id' => $sellingClub->id,
            'contract_id' => $activeContract->id, 'position' => 'GK',
            'potential' => 200, 'value' => 50000,
        ]);
        $tooExpensive = Player::factory()->create([
            'instance_id' => 1, 'club_id' => $sellingClub->id,
            'contract_id' => $activeContract->id, 'position' => 'CB',
            'potential' => 190, 'value' => 100001,
        ]);
        foreach ([$listed, $wrongPosition, $tooExpensive] as $player) {
            TransferList::factory()->create([
                'player_id' => $player->id,
                'club_id' => $sellingClub->id,
                'transfer_type' => TransferType::PERMANENT_TRANSFER->value,
            ]);
        }

        $free = Player::factory()->create([
            'instance_id' => 1, 'club_id' => null, 'contract_id' => null,
            'position' => 'CB', 'potential' => 100,
        ]);
        Player::factory()->create([
            'instance_id' => 1, 'club_id' => null, 'contract_id' => null,
            'position' => 'GK', 'potential' => 200,
        ]);
        Player::factory()->create([
            'instance_id' => 2, 'club_id' => null, 'contract_id' => null,
            'position' => 'CB', 'potential' => 200,
        ]);
        Player::factory()->create([
            'instance_id' => 1, 'club_id' => $sellingClub->id, 'contract_id' => null,
            'position' => 'CB', 'potential' => 250,
        ]);

        $listedResult = $repository->findListedPlayer(
            $buyingClub,
            TransferType::PERMANENT_TRANSFER,
            'CB',
            100000
        );
        $freeResult = $repository->findFreePlayerForPosition($buyingClub, 'CB');

        $this->assertSame($listed->id, $listedResult?->id);
        $this->assertSame($free->id, $freeResult?->id);
    }

    #[Test]
    public function unprotected_contract_search_uses_inclusive_context_date_boundaries_and_stable_ordering(): void
    {
        $repository = $this->repositoryWithContext('2024-01-01');
        $buyingClub = Club::factory()->create(['id' => 40, 'instance_id' => 1, 'rank' => 1]);
        $sellingClub = Club::factory()->create(['id' => 41, 'instance_id' => 1]);
        $startContract = PlayerContract::factory()->create([
            'contract_start' => '2023-01-01', 'contract_end' => '2024-01-01',
        ]);
        $endContract = PlayerContract::factory()->create([
            'contract_start' => '2023-01-01', 'contract_end' => '2024-07-01',
        ]);
        $beforeContract = PlayerContract::factory()->create([
            'contract_start' => '2023-01-01', 'contract_end' => '2023-12-31',
        ]);
        $outsideContract = PlayerContract::factory()->create([
            'contract_start' => '2023-01-01', 'contract_end' => '2024-07-02',
        ]);
        Player::factory()->create([
            'instance_id' => 1, 'club_id' => $sellingClub->id, 'contract_id' => $beforeContract->id,
            'position' => 'CB', 'potential' => 250, 'value' => 1,
        ]);
        $first = Player::factory()->create([
            'instance_id' => 1, 'club_id' => $sellingClub->id, 'contract_id' => $startContract->id,
            'position' => 'CB', 'potential' => 120, 'value' => 50000,
        ]);
        $second = Player::factory()->create([
            'instance_id' => 1, 'club_id' => $sellingClub->id, 'contract_id' => $endContract->id,
            'position' => 'CB', 'potential' => 120, 'value' => 50000,
        ]);
        Player::factory()->create([
            'instance_id' => 1, 'club_id' => $sellingClub->id, 'contract_id' => $startContract->id,
            'position' => 'CB', 'potential' => 200, 'value' => 1000001,
        ]);
        Player::factory()->create([
            'instance_id' => 1, 'club_id' => $sellingClub->id, 'contract_id' => $outsideContract->id,
            'position' => 'CB', 'potential' => 200,
        ]);

        $player = $repository->findExpiringContractTarget($buyingClub, 'CB', 1000000);

        $this->assertSame(min($first->id, $second->id), $player?->id);
        $this->assertNull($repository->findExpiringContractTarget($buyingClub, 'CB', 0));
    }

    #[Test]
    public function luxury_free_search_works_when_club_has_no_player_in_that_position(): void
    {
        $repository = $this->repositoryWithContext();
        $club = Club::factory()->create(['id' => 50, 'instance_id' => 1, 'rank' => 1]);
        $freePlayer = Player::factory()->create([
            'instance_id' => 1, 'club_id' => null, 'contract_id' => null,
            'position' => 'CB', 'potential' => 100,
        ]);

        $player = $repository->findFreePlayerForPosition($club, 'CB', true);

        $this->assertSame($freePlayer->id, $player?->id);
    }

    #[Test]
    public function searchable_column_map_stays_in_sync_with_player_fields(): void
    {
        $expected = array_merge(
            PlayerFields::TECHNICAL_FIELDS,
            PlayerFields::MENTAL_FIELDS,
            PlayerFields::PHYSICAL_FIELDS,
            PlayerFields::PERSON_ATTRIBUTE_CATEGORIES,
        );
        $constant = (new \ReflectionClass(PlayerSearchQuery::class))
            ->getReflectionConstant('SEARCH_COLUMNS');

        $this->assertNotFalse($constant);
        $actual = $constant->getValue();
        sort($expected);
        sort($actual);

        $this->assertSame($expected, $actual);
    }

    #[Test]
    public function searches_fail_clearly_when_required_context_is_missing(): void
    {
        app(GameContext::class)->set(null, null, null);

        $this->expectException(\RuntimeException::class);
        app(TransferSearchRepository::class)->findFreePlayerForPosition(
            new Club(['rank' => 1]),
            'CB',
        );
    }

    #[Test]
    public function position_search_requires_the_context_date(): void
    {
        app(GameContext::class)->set(1, null, null);

        $this->expectException(\RuntimeException::class);
        app(TransferSearchRepository::class)->findTransferTargetsByPosition(
            new Club(['rank' => 1]),
            'CB',
        );
    }

    #[Test]
    public function listed_search_keeps_transfer_types_isolated(): void
    {
        $repository = $this->repositoryWithContext();
        $buyingClub = Club::factory()->create(['instance_id' => 1]);
        $sellingClub = Club::factory()->create(['instance_id' => 1]);
        $permanent = Player::factory()->create([
            'instance_id' => 1, 'club_id' => $sellingClub->id, 'position' => 'CB',
            'potential' => 100, 'value' => 1,
        ]);
        $loan = Player::factory()->create([
            'instance_id' => 1, 'club_id' => $sellingClub->id, 'position' => 'CB',
            'potential' => 200, 'value' => 1,
        ]);
        TransferList::factory()->create([
            'player_id' => $permanent->id, 'club_id' => $sellingClub->id,
            'transfer_type' => TransferType::PERMANENT_TRANSFER->value,
        ]);
        TransferList::factory()->create([
            'player_id' => $loan->id, 'club_id' => $sellingClub->id,
            'transfer_type' => TransferType::LOAN_TRANSFER->value,
        ]);

        $player = $repository->findListedPlayer(
            $buyingClub,
            TransferType::PERMANENT_TRANSFER,
            'CB',
            100,
        );

        $this->assertSame($permanent->id, $player?->id);
    }

    #[Test]
    public function singular_search_adds_a_limit_clause(): void
    {
        $repository = $this->repositoryWithContext();
        $club = Club::factory()->create(['instance_id' => 1, 'rank' => 1]);
        Player::factory()->create([
            'instance_id' => 1, 'club_id' => null, 'contract_id' => null,
            'position' => 'CB', 'potential' => 100,
        ]);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        $repository->findFreePlayerForPosition($club, 'CB');

        $this->assertTrue(collect($queries)->contains(
            fn (string $sql): bool => str_contains($sql, 'limit 1')
        ));
    }

    private function repositoryWithContext(string $instanceDate = '2024-01-01'): TransferSearchRepository
    {
        app(GameContext::class)->set(1, null, $instanceDate);

        return app(TransferSearchRepository::class);
    }
}
