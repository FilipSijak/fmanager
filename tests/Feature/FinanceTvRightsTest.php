<?php

namespace Tests\Feature;

use App\EntityTransactionType;
use App\GameEntityType;
use App\Models\Account;
use App\Models\Club;
use App\Models\Competition;
use App\Models\GameEntity;
use App\Models\GameEntityAccount;
use App\Models\Instance;
use App\Models\Season;
use App\Services\FinanceService\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinanceTvRightsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_pays_tournament_tv_rights_based_on_games_played_and_only_once(): void
    {
        [$instance, $club, $tvAccount] = $this->createFinanceScenario();
        $competition = Competition::factory()->create([
            'id' => 1,
            'instance_id' => $instance->id,
            'rank' => 10000,
            'type' => 'tournament',
            'groups' => 0,
            'clubs_number' => 4,
        ]);
        $membershipId = DB::table('competition_season')->insertGetId([
            'instance_id' => $instance->id,
            'competition_id' => $competition->id,
            'season_id' => 1,
            'club_id' => $club->id,
            'played' => 2,
        ]);

        app(FinanceService::class)->payTournamentTvRights($instance);
        app(FinanceService::class)->payTournamentTvRights($instance);

        $this->assertSame(26_666_667, $club->account->fresh()->balance);
        $this->assertSame(973_333_333, $tvAccount->fresh()->balance);
        $this->assertDatabaseCount('finance_transactions_entities', 1);
        $this->assertDatabaseHas('finance_transactions_entities', [
            'event_type' => EntityTransactionType::TV_REVENUE->value,
            'event_id' => $membershipId,
            'amount' => 26_666_667,
        ]);
    }

    #[Test]
    public function it_pays_full_tv_rights_for_league_memberships_at_season_start(): void
    {
        [$instance, $club, $tvAccount] = $this->createFinanceScenario();
        $competition = Competition::factory()->create([
            'id' => 1,
            'instance_id' => $instance->id,
            'rank' => 10000,
            'type' => 'league',
            'groups' => null,
            'clubs_number' => 4,
        ]);
        DB::table('competition_season')->insert([
            'instance_id' => $instance->id,
            'competition_id' => $competition->id,
            'season_id' => 1,
            'club_id' => $club->id,
            'played' => 0,
        ]);

        app(FinanceService::class)->payLeagueTvRights($instance);

        $this->assertSame(40_000_000, $club->account->fresh()->balance);
        $this->assertSame(960_000_000, $tvAccount->fresh()->balance);
    }

    /**
     *  array{0: Instance, 1: Club, 2: GameEntityAccount}
     */
    private function createFinanceScenario(): array
    {
        $instance = Instance::factory()->create([
            'id' => 1,
            'season_id' => 1,
            'instance_date' => '2027-06-15',
        ]);
        $season = Season::factory()->create([
            'id' => 1,
            'instance_id' => $instance->id,
            'start_date' => '2026-08-15',
            'end_date' => '2027-06-15',
        ]);
        $club = Club::factory()->create([
            'id' => 1,
            'instance_id' => $instance->id,
            'rank' => 20,
        ]);
        $instance->forceFill(['club_id' => $club->id])->saveQuietly();
        Account::factory()->create([
            'club_id' => $club->id,
            'balance' => 0,
            'future_balance' => 0,
        ]);
        $tvBroadcaster = GameEntity::factory()->create([
            'instance_id' => $instance->id,
            'type' => GameEntityType::TV_BROADCASTER,
            'name' => 'TV Broadcaster',
        ]);
        $tvAccount = GameEntityAccount::factory()->create([
            'game_entity_id' => $tvBroadcaster->id,
            'instance_id' => $instance->id,
            'balance' => 1_000_000_000,
            'future_balance' => 1_000_000_000,
        ]);

        return [$instance, $club->load('account'), $tvAccount];
    }
}
