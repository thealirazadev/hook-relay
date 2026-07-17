<?php

use App\Models\User;

it('renders a branded 404 page', function () {
    $this->actingAs(User::factory()->create())
        ->get('/no-such-page')
        ->assertNotFound()
        ->assertSee('Not found')
        ->assertSee('Back to the dashboard');
});

it('keeps the ingest endpoint returning json for a missing route', function () {
    $this->post('/ingest/unknown-key-that-does-not-exist')
        ->assertStatus(404)
        ->assertJson(['error' => ['code' => 'unknown_source']]);
});
