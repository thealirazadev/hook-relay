<?php

use App\Models\Delivery;
use App\Models\User;
use App\Models\WebhookEvent;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('shows delivery status counts', function () {
    Delivery::factory()->count(2)->create(['status' => Delivery::STATUS_PENDING]);
    Delivery::factory()->delivered()->count(3)->create();
    Delivery::factory()->dead()->create();

    $this->get('/')
        ->assertOk()
        ->assertSee('Dashboard')
        ->assertSee('dead-letter queue');
});

it('renders a friendly empty state on a fresh install', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('No events yet');
});

it('lists recent events', function () {
    $event = WebhookEvent::factory()->create(['event_type' => 'charge.succeeded']);

    $this->get('/')
        ->assertOk()
        ->assertSee('charge.succeeded')
        ->assertSee(substr($event->id, 0, 12));
});
