<?php

namespace Tests\Integration\News;

use App\Models\Instance;
use App\Models\News;
use App\Services\NewsService\NewsType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NewsApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_unread_news_for_the_current_instance_by_default(): void
    {
        $instance = Instance::factory()->create([
            'id' => 1,
            'instance_hash' => 'current-instance',
            'season_id' => 1,
        ]);
        Instance::factory()->create([
            'id' => 2,
            'instance_hash' => 'other-instance',
            'season_id' => 1,
        ]);

        $unreadNews = $this->createNews([
            'instance_id' => $instance->id,
            'title' => 'Unread news',
            'is_read' => false,
        ]);
        $this->createNews([
            'instance_id' => $instance->id,
            'title' => 'Read news',
            'is_read' => true,
            'read_at' => now(),
        ]);
        $this->createNews([
            'instance_id' => 2,
            'title' => 'Other instance news',
            'is_read' => false,
        ]);

        $response = $this
            ->withHeaders(['instanceHash' => 'current-instance'])
            ->getJson('/api/news');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $unreadNews->id)
            ->assertJsonPath('data.0.title', 'Unread news')
            ->assertJsonPath('data.0.is_read', false);
    }

    #[Test]
    public function it_returns_all_current_instance_news_when_requested(): void
    {
        $instance = Instance::factory()->create([
            'id' => 1,
            'instance_hash' => 'current-instance',
            'season_id' => 1,
        ]);
        Instance::factory()->create([
            'id' => 2,
            'instance_hash' => 'other-instance',
            'season_id' => 1,
        ]);

        $this->createNews([
            'instance_id' => $instance->id,
            'title' => 'Unread news',
            'is_read' => false,
        ]);
        $this->createNews([
            'instance_id' => $instance->id,
            'title' => 'Read news',
            'is_read' => true,
            'read_at' => now(),
        ]);
        $this->createNews([
            'instance_id' => 2,
            'title' => 'Other instance news',
            'is_read' => false,
        ]);

        $response = $this
            ->withHeaders(['instanceHash' => 'current-instance'])
            ->getJson('/api/news?all=true');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $titles = collect($response->json('data'))->pluck('title')->all();

        $this->assertContains('Unread news', $titles);
        $this->assertContains('Read news', $titles);
        $this->assertNotContains('Other instance news', $titles);
    }

    #[Test]
    public function it_marks_current_instance_news_as_read(): void
    {
        $instance = Instance::factory()->create([
            'id' => 1,
            'instance_hash' => 'current-instance',
            'season_id' => 1,
        ]);
        $news = $this->createNews([
            'instance_id' => $instance->id,
            'is_read' => false,
            'read_at' => null,
        ]);

        $response = $this
            ->withHeaders(['instanceHash' => 'current-instance'])
            ->postJson("/api/news/{$news->id}/read");

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $news->id)
            ->assertJsonPath('data.is_read', true);

        $news->refresh();

        $this->assertTrue($news->is_read);
        $this->assertNotNull($news->read_at);
    }

    #[Test]
    public function it_does_not_mark_news_from_another_instance_as_read(): void
    {
        Instance::factory()->create([
            'id' => 1,
            'instance_hash' => 'current-instance',
            'season_id' => 1,
        ]);
        Instance::factory()->create([
            'id' => 2,
            'instance_hash' => 'other-instance',
            'season_id' => 1,
        ]);
        $otherInstanceNews = $this->createNews([
            'instance_id' => 2,
            'is_read' => false,
            'read_at' => null,
        ]);

        $response = $this
            ->withHeaders(['instanceHash' => 'current-instance'])
            ->postJson("/api/news/{$otherInstanceNews->id}/read");

        $response->assertNotFound();

        $otherInstanceNews->refresh();

        $this->assertFalse($otherInstanceNews->is_read);
        $this->assertNull($otherInstanceNews->read_at);
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
