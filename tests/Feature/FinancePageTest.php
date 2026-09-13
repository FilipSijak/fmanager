<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinancePageTest extends TestCase
{
    #[Test]
    public function it_renders_the_finance_page(): void
    {
        $this->withoutVite();

        $this->get('/finance')
            ->assertInertia(fn (Assert $page) => $page->component('Finance'));
    }
}
