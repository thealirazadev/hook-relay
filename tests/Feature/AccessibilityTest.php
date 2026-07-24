<?php

use App\Models\User;

it('renders a skip link and a focusable main landmark on every page', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Skip to main content')
        ->assertSee('href="#main"', false)
        ->assertSee('<main id="main" tabindex="-1">', false);
});

it('marks only the active nav item with aria-current', function () {
    $this->actingAs(User::factory()->create());

    $html = $this->get('/events')->assertOk()->getContent();

    expect(substr_count($html, 'aria-current="page"'))->toBe(1);
    expect($html)->toMatch('/<a href="[^"]*\/events"[^>]*\baria-current="page"/');
});
