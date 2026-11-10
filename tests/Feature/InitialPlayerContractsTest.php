<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Instance;
use App\Models\Player;
use App\Repositories\ContractRepository;
use App\Services\PersonService\PersonService;
use App\Support\GameContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class InitialPlayerContractsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function generated_club_players_receive_contracts_from_the_game_date_with_valid_terms(): void
    {
        [$instance, $club] = $this->world();

        app(PersonService::class)->createPlayersForClub($club);

        $players = Player::query()->forClub($club)->with('contract')->get();
        $this->assertNotEmpty($players);
        $this->assertDatabaseCount('players_contracts', $players->count());
        foreach ($players as $player) {
            $this->assertNotNull($player->contract);
            $this->assertSame('2026-08-31', $player->contract->contract_start);
            $end = CarbonImmutable::parse($player->contract->contract_end);
            $this->assertTrue($end->betweenIncluded('2027-02-28', '2031-08-31'));
            $this->assertGreaterThan(0, $player->contract->salary);
            $this->assertSame($instance->id, $player->instance_id);
        }
    }

    #[Test]
    public function generated_free_agents_have_no_contract_or_orphan_contract_records(): void
    {
        $this->world();

        app(PersonService::class)->createFreePlayers(3);

        $this->assertSame(3, Player::query()->whereNull('club_id')->whereNull('contract_id')->count());
        $this->assertDatabaseCount('players_contracts', 0);
    }

    #[Test]
    public function failed_contract_assignment_rolls_back_generated_players_people_and_progress(): void
    {
        [, $club] = $this->world();
        $this->partialMock(ContractRepository::class)->shouldReceive('createPlayerContract')
            ->once()->andThrow(new RuntimeException('Contract creation failed.'));
        try {
            app(PersonService::class)->createPlayersForClub($club);
            $this->fail('Expected contract creation to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Contract creation failed.', $exception->getMessage());
        }

        $this->assertDatabaseCount('players', 0);
        $this->assertDatabaseCount('people', 0);
        $this->assertDatabaseCount('players_progress', 0);
        $this->assertDatabaseCount('players_contracts', 0);
    }

    /** @return array{Instance, Club} */
    private function world(): array
    {
        $instance = Instance::factory()->create(['instance_date' => '2026-08-31']);
        $club = Club::factory()->create(['instance_id' => $instance->id, 'stadium_id' => 100, 'rank' => 10, 'rank_academy' => 10]);
        app(GameContext::class)->set($instance->id, 1, '2026-08-31');

        return [$instance, $club];
    }
}
