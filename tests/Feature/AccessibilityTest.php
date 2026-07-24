<?php

it('renders a skip link and a focusable main landmark on every page', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Skip to main content')
        ->assertSee('href="#main"', false)
        ->assertSee('<main id="main" tabindex="-1">', false);
});
