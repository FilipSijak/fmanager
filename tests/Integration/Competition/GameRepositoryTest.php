<?php

namespace Tests\Integration\Competition;

use App\Models\Club;
use App\Models\Game;
use App\Models\Instance;
use App\Models\Stadium;
use App\Repositories\GameRepository;
use App\Support\GameContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GameRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private GameContext $gameContext;

    private GameRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        Instance::factory()->create(['id' => 1]);
        Instance::factory()->create(['id' => 2]);
        $this->gameContext = app(GameContext::class);
        $this->gameContext->set(1, 10);
        $this->repository = app(GameRepository::class);
    }

    #[Test]
    public function it_returns_game_data_from_the_active_instance_and_season(): void
    {
        $homeClub = Club::factory()->create(['instance_id' => 1, 'name' => 'Home FC']);
        $awayClub = Club::factory()->create(['instance_id' => 1, 'name' => 'Away FC']);
        $stadium = Stadium::factory()->create(['instance_id' => 1, 'name' => 'Main Ground']);
        $game = Game::factory()->create([
            'instance_id' => 1,
            'season_id' => 10,
            'competition_id' => 1,
            'hometeam_id' => $homeClub->id,
            'awayteam_id' => $awayClub->id,
            'stadium_id' => $stadium->id,
            'winner' => 1,
            'home_team_goals' => 2,
            'away_team_goals' => 0,
        ]);

        $result = $this->repository->getFullGameData($game->id);

        $this->assertSame('Home FC', $result['home_team']);
        $this->assertSame('Away FC', $result['away_team']);
        $this->assertSame('Main Ground', $result['stadium_name']);
        $this->assertSame(2, $result['home_team_goals']);
    }

    #[Test]
    public function it_returns_null_for_missing_or_out_of_context_games(): void
    {
        $homeClub = Club::factory()->create(['instance_id' => 2]);
        $awayClub = Club::factory()->create(['instance_id' => 2]);
        $stadium = Stadium::factory()->create(['instance_id' => 2]);
        $game = Game::factory()->create([
            'instance_id' => 2,
            'season_id' => 10,
            'competition_id' => 1,
            'hometeam_id' => $homeClub->id,
            'awayteam_id' => $awayClub->id,
            'stadium_id' => $stadium->id,
        ]);

        $this->assertNull($this->repository->getFullGameData($game->id));
        $this->assertNull($this->repository->getFullGameData(999_999));
    }

    #[Test]
    public function it_rejects_joined_entities_from_another_instance(): void
    {
        $homeClub = Club::factory()->create(['instance_id' => 1]);
        $awayClub = Club::factory()->create(['instance_id' => 1]);
        $foreignStadium = Stadium::factory()->create(['instance_id' => 2]);
        $game = Game::factory()->create([
            'instance_id' => 1,
            'season_id' => 10,
            'competition_id' => 1,
            'hometeam_id' => $homeClub->id,
            'awayteam_id' => $awayClub->id,
            'stadium_id' => $foreignStadium->id,
        ]);

        $this->assertNull($this->repository->getFullGameData($game->id));
    }
}
