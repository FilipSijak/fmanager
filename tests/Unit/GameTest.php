<?php

namespace Tests\Unit;

use App\Models\Instance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameTest extends TestCase
{
    use RefreshDatabase;
    /** @test */
    public function it_can_create_a_new_game()
    {
        $game = Instance::factory()->make();
        $game->save();

        $this->assertDatabaseHas('instances', ['club_id' => $game->club_id]);
    }
}
