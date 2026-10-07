<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TacticsPageTest extends TestCase
{
    #[Test]
    public function it_renders_the_tactics_page(): void
    {
        $this->withoutVite();

        $this->get('/tactics')
            ->assertInertia(fn (Assert $page) => $page->component('tactics/Tactics'));
    }
}
