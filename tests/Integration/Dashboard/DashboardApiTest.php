<?php

namespace Tests\Integration\Dashboard;

use App\Models\Account;
use App\Models\Club;
use App\Models\Instance;
use App\Models\News;
use App\Services\NewsService\NewsType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_dashboard_data_for_the_current_manager_club(): void
    {
        $managedClub = Club::factory()->create([
            'id' => 10,
            'name' => 'Managed FC',
            'rank' => 12,
            'rank_academy' => 34,
            'rank_training' => 56,
        ]);
        $otherClub = Club::factory()->create([
            'id' => 20,
            'name' => 'Other FC',
        ]);

        $instance = Instance::factory()->create([
            'id' => 1,
            'instance_hash' => 'dashboard-instance',
            'season_id' => 3,
            'club_id' => $managedClub->id,
            'instance_date' => '2024-08-15',
        ]);

        Account::factory()->create([
            'club_id' => $managedClub->id,
            'balance' => 1000,
            'future_balance' => 2000,
            'transfer_budget' => 3000,
            'salaries_yearly_budget' => 4000,
        ]);

        $readManagedClubNews = $this->createNews([
            'instance_id' => $instance->id,
            'club_id' => $managedClub->id,
            'title' => 'Read managed club news',
            'is_read' => true,
            'read_at' => now(),
            'published_at' => now()->subSeconds(30),
        ]);
        $managedClubNews = $this->createNews([
            'instance_id' => $instance->id,
            'club_id' => $managedClub->id,
            'title' => 'Managed club news',
            'published_at' => now()->subMinute(),
        ]);
        $globalNews = $this->createNews([
            'instance_id' => $instance->id,
            'club_id' => null,
            'title' => 'Global news',
            'published_at' => now()->subMinutes(2),
        ]);
        $this->createNews([
            'instance_id' => $instance->id,
            'club_id' => $otherClub->id,
            'title' => 'Other club news',
        ]);
        $this->createNews([
            'instance_id' => 2,
            'club_id' => $managedClub->id,
            'title' => 'Other instance news',
        ]);

        $response = $this
            ->withHeaders(['instanceHash' => 'dashboard-instance'])
            ->getJson('/api/dashboard');

        $response
            ->assertOk()
            ->assertJsonPath('data.instance.id', $instance->id)
            ->assertJsonPath('data.instance.date', '2024-08-15')
            ->assertJsonPath('data.instance.season_id', 3)
            ->assertJsonPath('data.club.id', $managedClub->id)
            ->assertJsonPath('data.club.name', 'Managed FC')
            ->assertJsonPath('data.club.rank', 12)
            ->assertJsonPath('data.club.rank_academy', 34)
            ->assertJsonPath('data.club.rank_training', 56)
            ->assertJsonPath('data.account.balance', 1000)
            ->assertJsonPath('data.account.future_balance', 2000)
            ->assertJsonPath('data.account.transfer_budget', 3000)
            ->assertJsonPath('data.account.salaries_yearly_budget', 4000)
            ->assertJsonCount(3, 'data.news');

        $news = collect($response->json('data.news'));
        $newsIds = $news->pluck('id')->all();
        $newsTitles = $news->pluck('title')->all();

        $this->assertSame([$readManagedClubNews->id, $managedClubNews->id, $globalNews->id], $newsIds);
        $this->assertTrue($news->firstWhere('title', 'Read managed club news')['is_read']);
        $this->assertContains('Managed club news', $newsTitles);
        $this->assertContains('Global news', $newsTitles);
        $this->assertNotContains('Other club news', $newsTitles);
        $this->assertNotContains('Other instance news', $newsTitles);
    }

    #[Test]
    public function it_limits_dashboard_news_to_thirty_items(): void
    {
        $managedClub = Club::factory()->create(['id' => 10]);

        $instance = Instance::factory()->create([
            'id' => 1,
            'instance_hash' => 'dashboard-instance',
            'club_id' => $managedClub->id,
        ]);

        Account::factory()->create(['club_id' => $managedClub->id]);

        for ($i = 1; $i <= 31; $i++) {
            $this->createNews([
                'instance_id' => $instance->id,
                'club_id' => $managedClub->id,
                'title' => "News {$i}",
                'published_at' => now()->subMinutes($i),
            ]);
        }

        $response = $this
            ->withHeaders(['instanceHash' => 'dashboard-instance'])
            ->getJson('/api/dashboard');

        $response
            ->assertOk()
            ->assertJsonCount(30, 'data.news');

        $newsTitles = collect($response->json('data.news'))->pluck('title')->all();

        $this->assertContains('News 1', $newsTitles);
        $this->assertNotContains('News 31', $newsTitles);
    }

    private function createNews(array $overrides = []): News
    {
        return News::create(array_merge([
            'instance_id' => 1,
            'season_id' => 1,
            'club_id' => 1,
            'competition_id' => null,
            'title' => 'News title',
            'content' => 'News content',
            'type' => NewsType::Transfer->value,
            'priority' => 5,
            'published_at' => now(),
            'is_read' => false,
            'read_at' => null,
        ], $overrides));
    }
}
