<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlayerProfilePageTest extends TestCase
{
    #[Test]
    public function it_renders_the_player_profile_page_with_the_requested_player_id(): void
    {
        $this->withoutVite();

        $this->get('/player-profile/42')
            ->assertInertia(fn (Assert $page) => $page
                ->component('player-profile/PlayerProfile')
                ->where('playerId', 42));
    }
}
