<?php

namespace Tests\Feature;

use App\EntityTransactionType;
use App\Events\PostMatch;
use App\GameEntityType;
use App\Models\Account;
use App\Models\Club;
use App\Models\Competition;
use App\Models\Game;
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
        [$instance, $club] = $this->createFinanceScenario();
        $competition = Competition::factory()->create([
            'id' => 1,
            'instance_id' => $instance->id,
            'rank' => 10000,
            'type' => 'tournament',
            'groups' => 0,
            'clubs_number' => 4,
        ]);
        $competitionAccount = $this->createCompetitionAccount($instance, $competition);
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
        $this->assertSame(973_333_333, $competitionAccount->fresh()->balance);
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
        [$instance, $club] = $this->createFinanceScenario();
        $competition = Competition::factory()->create([
            'id' => 1,
            'instance_id' => $instance->id,
            'rank' => 10000,
            'type' => 'league',
            'groups' => null,
            'clubs_number' => 4,
        ]);
        $competitionAccount = $this->createCompetitionAccount($instance, $competition);
        DB::table('competition_season')->insert([
            'instance_id' => $instance->id,
            'competition_id' => $competition->id,
            'season_id' => 1,
            'club_id' => $club->id,
            'played' => 0,
        ]);

        app(FinanceService::class)->payLeagueTvRights($instance);

        $this->assertSame(40_000_000, $club->account->fresh()->balance);
        $this->assertSame(960_000_000, $competitionAccount->fresh()->balance);
    }

    #[Test]
    public function it_pays_league_prizes_from_the_competition_entity_only_once(): void
    {
        [$instance, $club] = $this->createFinanceScenario();
        $competition = Competition::factory()->create([
            'id' => 1,
            'instance_id' => $instance->id,
            'rank' => 10_000,
            'type' => 'league',
            'groups' => null,
            'clubs_number' => 4,
        ]);
        $competitionAccount = $this->createCompetitionAccount($instance, $competition);
        $membershipId = DB::table('competition_season')->insertGetId([
            'instance_id' => $instance->id,
            'competition_id' => $competition->id,
            'season_id' => 1,
            'club_id' => $club->id,
            'played' => 38,
        ]);

        app(FinanceService::class)->payLeagueCompetitionPrizes($instance);
        app(FinanceService::class)->payLeagueCompetitionPrizes($instance);

        $this->assertSame(20_000_000, $club->account->fresh()->balance);
        $this->assertSame(980_000_000, $competitionAccount->fresh()->balance);
        $this->assertDatabaseHas('finance_transactions_entities', [
            'event_type' => EntityTransactionType::PRIZE->value,
            'event_id' => $membershipId,
            'amount' => 20_000_000,
        ]);
        $this->assertDatabaseCount('finance_transactions_entities', 1);
    }

    #[Test]
    public function it_pays_continental_prizes_for_rounds_and_results_from_the_competition_entity(): void
    {
        [$instance, $club] = $this->createFinanceScenario();
        $opponent = Club::factory()->create([
            'id' => 2,
            'instance_id' => $instance->id,
        ]);
        $competition = Competition::factory()->create([
            'id' => 1,
            'instance_id' => $instance->id,
            'rank' => 10_000,
            'type' => 'tournament',
            'competition_scope' => 'continental',
            'groups' => 1,
            'clubs_number' => 32,
        ]);
        $competitionAccount = $this->createCompetitionAccount($instance, $competition);
        $membershipId = DB::table('competition_season')->insertGetId([
            'instance_id' => $instance->id,
            'competition_id' => $competition->id,
            'season_id' => 1,
            'club_id' => $club->id,
            'played' => 1,
        ]);
        $membershipId = DB::table('competition_season')->where('instance_id', $instance->id)->where('competition_id', $competition->id)->where('club_id', $club->id)->value('id');
        DB::table('games')->insert([
            'instance_id' => $instance->id,
            'season_id' => 1,
            'competition_id' => $competition->id,
            'hometeam_id' => $club->id,
            'awayteam_id' => $opponent->id,
            'winner' => 3,
            'status' => 'completed',
            'home_team_goals' => 1,
            'away_team_goals' => 1,
        ]);
        DB::table('tournament_knockout')->insert([
            'id' => 1,
            'instance_id' => $instance->id,
            'competition_id' => $competition->id,
            'season_id' => 1,
            'participant_count' => 16,
            'bracket_size' => 16,
        ]);
        DB::table('tournament_knockout_rounds')->insert([
            'id' => 1,
            'tournament_knockout_id' => 1,
            'round_number' => 1,
            'bracket_side' => 'final',
            'name' => 'final',
            'number_of_legs' => 1,
        ]);
        DB::table('tournament_knockout_ties')->insert([
            'id' => 1,
            'round_id' => 1,
            'position' => 1,
            'home_club_id' => $club->id,
            'away_club_id' => $opponent->id,
        ]);
        DB::table('games')->insert([
            'instance_id' => $instance->id,
            'season_id' => 1,
            'competition_id' => $competition->id,
            'hometeam_id' => $club->id,
            'awayteam_id' => $opponent->id,
            'winner' => 1,
            'status' => 'completed',
            'home_team_goals' => 2,
            'away_team_goals' => 1,
            'knockout_tie_id' => 1,
            'leg_number' => 1,
        ]);

        app(FinanceService::class)->payContinentalCompetitionPrizes($instance);
        app(FinanceService::class)->payContinentalCompetitionPrizes($instance);

        $this->assertSame(11_000_000, $club->account->fresh()->balance);
        $this->assertSame(989_000_000, $competitionAccount->fresh()->balance);
        $this->assertDatabaseHas('finance_transactions_entities', [
            'event_type' => EntityTransactionType::PRIZE->value,
            'event_id' => $membershipId,
            'amount' => 11_000_000,
        ]);
        $this->assertDatabaseCount('finance_transactions_entities', 1);
    }

    #[Test]
    public function it_pays_continental_match_rewards_and_group_round_reward_after_the_group_ends(): void
    {
        [$instance, $club] = $this->createFinanceScenario();
        $opponent = Club::factory()->create([
            'id' => 2,
            'instance_id' => $instance->id,
        ]);
        $competition = Competition::factory()->create([
            'id' => 1,
            'instance_id' => $instance->id,
            'rank' => 10_000,
            'type' => 'tournament',
            'competition_scope' => 'continental',
            'groups' => 1,
            'clubs_number' => 32,
        ]);
        $competitionAccount = $this->createCompetitionAccount($instance, $competition);
        DB::table('competition_season')->insert([
            ['instance_id' => $instance->id, 'competition_id' => $competition->id, 'season_id' => 1, 'club_id' => $club->id, 'group_id' => 1],
            ['instance_id' => $instance->id, 'competition_id' => $competition->id, 'season_id' => 1, 'club_id' => $opponent->id, 'group_id' => 1],
        ]);
        $membershipId = DB::table('competition_season')->where('instance_id', $instance->id)->where('competition_id', $competition->id)->where('club_id', $club->id)->value('id');
        DB::table('games')->insert([
            ['id' => 1, 'instance_id' => $instance->id, 'season_id' => 1, 'competition_id' => $competition->id, 'hometeam_id' => $club->id, 'awayteam_id' => $opponent->id, 'winner' => 1, 'status' => 'completed', 'home_team_goals' => 1, 'away_team_goals' => 0],
            ['id' => 2, 'instance_id' => $instance->id, 'season_id' => 1, 'competition_id' => $competition->id, 'hometeam_id' => $opponent->id, 'awayteam_id' => $club->id, 'winner' => null, 'status' => 'scheduled', 'home_team_goals' => null, 'away_team_goals' => null],
        ]);

        event(new PostMatch(Game::findOrFail(1)));

        $this->assertSame(2_000_000, $club->account->fresh()->balance);
        $this->assertSame(998_000_000, $competitionAccount->fresh()->balance);
        $this->assertDatabaseCount('finance_transactions_entities', 1);

        DB::table('games')->where('id', 2)->update([
            'winner' => 1,
            'status' => 'completed',
            'home_team_goals' => 1,
            'away_team_goals' => 0,
        ]);
        event(new PostMatch(Game::findOrFail(2)));

        $this->assertSame(6_000_000, $club->account->fresh()->balance);
        $this->assertSame(994_000_000, $competitionAccount->fresh()->balance);
        $this->assertDatabaseHas('finance_transactions_entities', ['event_type' => EntityTransactionType::CONTINENTAL_ROUND_PRIZE->value, 'event_id' => $membershipId, 'amount' => 4_000_000]);
        $this->assertDatabaseCount('finance_transactions_entities', 2);
    }

    /**
     *  array{0: Instance, 1: Club}
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

        return [$instance, $club->load('account')];
    }

    private function createCompetitionAccount(Instance $instance, Competition $competition): GameEntityAccount
    {
        $entity = GameEntity::factory()->create([
            'instance_id' => $instance->id,
            'competition_id' => $competition->id,
            'type' => GameEntityType::COMPETITION,
            'name' => $competition->name,
        ]);

        return GameEntityAccount::factory()->create([
            'game_entity_id' => $entity->id,
            'instance_id' => $instance->id,
            'balance' => 1_000_000_000,
            'future_balance' => 1_000_000_000,
        ]);
    }
}
