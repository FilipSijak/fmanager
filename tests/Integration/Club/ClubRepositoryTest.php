<?php

namespace Tests\Integration\Club;

use App\DataModels\ClubFinancialSummary;
use App\Models\Account;
use App\Models\Club;
use App\Models\Instance;
use App\Models\Person;
use App\Models\Player;
use App\Models\PlayerContract;
use App\Repositories\ClubRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClubRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private ClubRepository $repository;

    private Instance $instance;

    private Club $club;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(ClubRepository::class);
        $this->instance = Instance::factory()->create(['id' => 1, 'instance_date' => '2026-08-28']);
        $this->club = Club::factory()->create(['id' => 10, 'instance_id' => $this->instance->id]);
    }

    #[Test]
    public function it_finds_a_club_only_in_the_requested_instance(): void
    {
        $this->assertSame($this->club->id, $this->repository->findForInstance(10, 1)?->id);
        $this->assertNull($this->repository->findForInstance(10, 999));
    }

    #[Test]
    public function it_returns_only_active_squad_players_in_position_order(): void
    {
        $striker = $this->player('ST', 100, 'Zulu');
        $defender = $this->player('CB', 80, 'Alpha');
        $this->player('GK', 60, 'Retired', ['is_retired' => true]);
        $otherClub = Club::factory()->create(['instance_id' => 1]);
        $this->player('AM', 90, 'Other', ['club_id' => $otherClub->id]);

        $players = $this->repository->getSquadByPosition(10);

        $this->assertSame([$defender->id, $striker->id], $players->pluck('id')->all());
        $this->assertTrue($players->every->relationLoaded('person'));
        $this->assertTrue($players->every->relationLoaded('contract'));
    }

    #[Test]
    public function it_builds_an_active_squad_summary(): void
    {
        $this->player('CB', 80, 'First', [
            'value' => 1_000, 'dob' => '2000-08-28', 'salary' => 100,
            'contract_end' => '2027-06-30',
        ]);
        $this->player('ST', 100, 'Second', [
            'value' => 3_000, 'dob' => '2002-08-28', 'salary' => 200,
            'contract_end' => '2028-06-30',
        ]);
        $this->player('GK', 200, 'Retired', ['is_retired' => true, 'value' => 99_000, 'salary' => 900]);

        $this->assertSame([
            'player_count' => 2,
            'average_age' => 25.0,
            'average_potential' => 90.0,
            'total_value' => 4_000,
            'weekly_wages' => 300,
            'contracts_expiring_within_year' => 1,
        ], $this->repository->getSquadSummary(10));
    }

    #[Test]
    public function it_calculates_only_whitelisted_position_attribute_averages(): void
    {
        $this->player('CB', 80, 'First', ['pace' => 10, 'tackling' => 16]);
        $this->player('CB', 100, 'Second', ['pace' => 15, 'tackling' => 19]);
        $this->player('ST', 200, 'Striker', ['pace' => 20, 'tackling' => 1]);

        $this->assertSame(
            ['potential' => 90, 'pace' => 12, 'tackling' => 17],
            $this->repository->getPositionAttributeAverages(10, 'CB', ['potential', 'pace', 'tackling'])
        );

        $this->expectException(InvalidArgumentException::class);
        $this->repository->getPositionAttributeAverages(10, 'CB', ['instance_id']);
    }

    #[Test]
    public function it_returns_finances_with_annualized_active_player_wages(): void
    {
        Account::factory()->create([
            'club_id' => 10,
            'balance' => 1_000,
            'future_balance' => 900,
            'allowed_debt' => 500,
            'transfer_budget' => 700,
            'salaries_yearly_budget' => 20_000,
        ]);
        $this->player('CB', 80, 'First', ['salary' => 100]);
        $this->player('ST', 100, 'Second', ['salary' => 200]);
        $this->player('GK', 100, 'Retired', ['salary' => 900, 'is_retired' => true]);

        $summary = $this->repository->getTransferBudgetAndBalance(10);

        $this->assertInstanceOf(ClubFinancialSummary::class, $summary);
        $this->assertSame(1_000, $summary->balance);
        $this->assertSame(15_600, $summary->annualPlayerWages);
        $this->assertSame(4_400, $summary->remainingAnnualSalaryBudget);
    }

    #[Test]
    public function it_returns_null_finances_without_an_account(): void
    {
        $this->assertNull($this->repository->getTransferBudgetAndBalance(10));
    }

    private function player(string $position, int $potential, string $lastName, array $attributes = []): Player
    {
        $person = Person::factory()->create([
            'instance_id' => 1,
            'last_name' => $lastName,
            'dob' => $attributes['dob'] ?? '2000-01-01',
        ]);
        $contract = PlayerContract::factory()->create([
            'salary' => $attributes['salary'] ?? 0,
            'contract_end' => $attributes['contract_end'] ?? '2030-06-30',
        ]);

        unset($attributes['dob'], $attributes['salary'], $attributes['contract_end']);

        return Player::factory()->create(array_merge([
            'instance_id' => 1,
            'person_id' => $person->id,
            'club_id' => 10,
            'contract_id' => $contract->id,
            'position' => $position,
            'potential' => $potential,
        ], $attributes));
    }
}
