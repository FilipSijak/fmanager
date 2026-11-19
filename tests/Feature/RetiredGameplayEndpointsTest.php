<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Club;
use App\Models\Competition;
use App\Models\Game;
use App\Models\Instance;
use App\Models\Player;
use App\Models\Season;
use App\Models\Stadium;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RetiredGameplayEndpointsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[DataProvider('retiredEndpoints')]
    public function retired_endpoints_return_not_found_without_changing_the_save(string $endpoint, bool $unemployed, bool $foreignSave): void
    {
        $user = User::factory()->create();
        $instance = Instance::factory()->create(['user_id' => $user->id, 'instance_date' => '2030-08-15']);
        $season = Season::factory()->create(['instance_id' => $instance->id]);
        $competition = Competition::factory()->create(['instance_id' => $instance->id, 'type' => 'league', 'groups' => null]);
        $stadium = Stadium::factory()->create(['instance_id' => $instance->id]);
        [$managedClub, $otherClub] = Club::factory()->count(2)->create(['instance_id' => $instance->id, 'stadium_id' => $stadium->id])->all();
        $instance->forceFill(['season_id' => $season->id, 'club_id' => $unemployed ? null : $managedClub->id])->save();
        $game = Game::factory()->create([
            'instance_id' => $instance->id, 'season_id' => $season->id, 'competition_id' => $competition->id,
            'hometeam_id' => $managedClub->id, 'awayteam_id' => $otherClub->id, 'match_start' => '2030-08-15 15:00:00',
            'stadium_id' => $stadium->id,
        ]);
        $player = Player::factory()->create(['instance_id' => $instance->id, 'club_id' => $managedClub->id]);
        foreach ([$managedClub, $otherClub] as $club) {
            Account::factory()->create(['club_id' => $club->id, 'balance' => 100000, 'future_balance' => 100000, 'transfer_budget' => 100000]);
            DB::table('competition_season')->insert([
                'instance_id' => $instance->id, 'season_id' => $season->id, 'competition_id' => $competition->id, 'club_id' => $club->id,
            ]);
        }
        $fixturesBefore = DB::table('games')->get()->toJson();
        $standingsBefore = DB::table('competition_season')->get()->toJson();
        $accountsBefore = DB::table('accounts')->get()->toJson();

        $this->actingAs($foreignSave ? User::factory()->create() : $user)
            ->withHeader('instanceHash', $instance->instance_hash)
            ->postJson(str_replace('{gameId}', (string) $game->id, $endpoint), [
                'home_team_goals' => 99, 'away_team_goals' => 0, 'match_start' => '2030-09-01 15:00:00',
                'source_club_id' => $otherClub->id, 'target_club_id' => $managedClub->id, 'player_id' => $player->id,
                'transfer_type' => 1, 'amount' => 10000, 'installments' => 0, 'salary' => 1000,
            ])->assertNotFound();

        $this->assertSame($fixturesBefore, DB::table('games')->get()->toJson());
        $this->assertSame($standingsBefore, DB::table('competition_season')->get()->toJson());
        $this->assertSame($accountsBefore, DB::table('accounts')->get()->toJson());
        $this->assertDatabaseHas('players', ['id' => $player->id, 'club_id' => $managedClub->id]);
        $this->assertDatabaseCount('transfers', 0);
        $this->assertDatabaseCount('transfer_financial_details', 0);
        $this->assertDatabaseCount('transfer_contract_offers', 0);
        $this->assertDatabaseCount('finance_transactions', 0);
        $this->assertDatabaseCount('finance_transactions_entities', 0);
        $this->assertDatabaseCount('matchday_settlements', 0);
        $this->assertDatabaseCount('game_player_stats', 0);
    }

    /** @return array<string, array{string, bool, bool}> */
    public static function retiredEndpoints(): array
    {
        $cases = [];

        foreach (['manager' => [false, false], 'unemployed' => [true, false], 'another save' => [false, true]] as $role => [$unemployed, $foreignSave]) {
            foreach (['/api/game/{gameId}/complete', '/api/game/{gameId}/postpone', '/api/game/{gameId}/cancel', '/api/transfer', '/api/transfer/free-transfer'] as $endpoint) {
                $cases[$role.' '.$endpoint] = [$endpoint, $unemployed, $foreignSave];
            }
        }

        return $cases;
    }
}
