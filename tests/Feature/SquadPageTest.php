<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SquadPageTest extends TestCase
{
    #[Test]
    public function it_renders_the_squad_page(): void
    {
        $this->withoutVite();

        $this->get('/squad')
            ->assertInertia(fn (Assert $page) => $page->component('squad/Squad'));
    }
}
