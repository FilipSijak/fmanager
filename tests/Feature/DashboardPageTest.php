<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Club;
use App\Models\Competition;
use App\Models\Game;
use App\Models\Instance;
use App\Models\News;
use App\Services\NewsService\NewsType;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    #[Test]
    public function it_renders_dashboard_data_from_the_dashboard_service(): void
    {
        $club = Club::factory()->create(['name' => 'Managed FC']);
        $instance = Instance::factory()->create(['club_id' => $club->id, 'season_id' => 1]);
        Account::factory()->create(['club_id' => $club->id]);
        News::create([
            'instance_id' => $instance->id, 'season_id' => 1, 'club_id' => $club->id,
            'title' => 'Welcome news', 'content' => 'Welcome to the club.',
            'type' => NewsType::Transfer->value, 'priority' => 1, 'published_at' => now(),
            'is_read' => false, 'read_at' => null,
        ]);
        $opponent = Club::factory()->create(['name' => 'Rival FC']);
        $league = Competition::factory()->create(['instance_id' => $instance->id, 'name' => 'Premier League']);
        Game::factory()->create([
            'instance_id' => $instance->id, 'season_id' => 1, 'competition_id' => $league->id,
            'hometeam_id' => $opponent->id, 'awayteam_id' => $club->id, 'stadium_id' => 1,
            'match_start' => Carbon::parse($instance->fresh()->instance_date)->addDays(3)->setTime(15, 0),
        ]);

        $this->actingAs($instance->user)
            ->withSession(['active_instance_hash' => $instance->instance_hash])
            ->get('/')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('game.date', $instance->fresh()->instance_date)
                ->where('dashboard.club.name', 'Managed FC')
                ->where('dashboard.news.0.title', 'Welcome news')
                ->where('dashboard.next_game.competition_name', 'Premier League')
                ->where('dashboard.next_game.home_team.name', 'Rival FC')
                ->where('dashboard.next_game.away_team.name', 'Managed FC'));
    }
}
