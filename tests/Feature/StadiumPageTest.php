<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StadiumPageTest extends TestCase
{
    #[Test]
    public function it_renders_the_fixed_camera_stadium_page(): void
    {
        $this->withoutVite();

        $this->get('/stadium')
            ->assertInertia(fn (Assert $page) => $page->component('Stadium'));
    }
}
