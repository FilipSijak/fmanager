<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StadiumSubPagesTest extends TestCase
{
    #[Test]
    public function it_renders_the_stadium_construction_page(): void
    {
        $this->withoutVite();

        $this->get('/stadium/construction')
            ->assertInertia(fn (Assert $page) => $page->component('StadiumConstruction'));
    }

    #[Test]
    public function it_renders_the_stadium_restaurants_page(): void
    {
        $this->withoutVite();

        $this->get('/stadium/restaurants')
            ->assertInertia(fn (Assert $page) => $page->component('StadiumRestaurants'));
    }

    #[Test]
    public function it_renders_the_stadium_bars_page(): void
    {
        $this->withoutVite();

        $this->get('/stadium/bars')
            ->assertInertia(fn (Assert $page) => $page->component('StadiumBars'));
    }

    #[Test]
    public function it_renders_the_stadium_shops_page(): void
    {
        $this->withoutVite();

        $this->get('/stadium/shops')
            ->assertInertia(fn (Assert $page) => $page->component('StadiumShops'));
    }
}
