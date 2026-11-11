<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Instance;
use App\Models\Player;
use App\Repositories\ContractRepository;
use App\Services\ContractService\ContractService;
use App\Services\PersonService\PersonConfig\Player\PlayerFields;
use App\Services\PlayerService\PlayerService;
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

        app(PlayerService::class)->createPlayersForClub($club);

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

        app(PlayerService::class)->createFreePlayers(3);

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
            app(PlayerService::class)->createPlayersForClub($club);
            $this->fail('Expected contract creation to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Contract creation failed.', $exception->getMessage());
        }

        $this->assertDatabaseCount('players', 0);
        $this->assertDatabaseCount('people', 0);
        $this->assertDatabaseCount('players_progress', 0);
        $this->assertDatabaseCount('players_contracts', 0);
    }

    #[Test]
    public function setup_salary_uses_current_ability_instead_of_a_players_ceiling(): void
    {
        [$instance, $club] = $this->world();
        $skills = array_fill_keys(array_merge(PlayerFields::TECHNICAL_FIELDS, PlayerFields::MENTAL_FIELDS, PlayerFields::PHYSICAL_FIELDS), 2);
        $player = Player::factory()->create($skills + ['instance_id' => $instance->id, 'club_id' => $club->id,
            'contract_id' => null, 'marketing_rank' => 20, 'potential' => 200, 'max_potential' => 200]);
        $player->person->forceFill(['dob' => '2000-01-01'])->save();

        app(ContractService::class)->createInitialPlayerContracts($instance->id, $club, collect([$player]));

        $this->assertSame(267, (int) $player->fresh()->contract->salary);
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
