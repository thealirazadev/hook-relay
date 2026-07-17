<?php

use App\Models\Delivery;
use App\Models\Destination;
use App\Models\Source;
use App\Models\User;
use App\Models\WebhookEvent;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('shows an empty state when there are no deliveries', function () {
    $this->get('/deliveries')->assertOk()->assertSee('No deliveries yet');
});

it('filters deliveries by status', function () {
    $dead = Delivery::factory()->dead()->create();
    $delivered = Delivery::factory()->delivered()->create();

    $this->get('/deliveries?status=dead')
        ->assertOk()
        ->assertSee('/deliveries/'.$dead->id)
        ->assertDontSee('/deliveries/'.$delivered->id);
});

it('filters deliveries by source', function () {
    $sourceA = Source::factory()->create();
    $eventA = WebhookEvent::factory()->for($sourceA)->create();
    $deliveryA = Delivery::factory()->for($eventA, 'event')->create();

    $deliveryB = Delivery::factory()->create();

    $this->get('/deliveries?source_id='.$sourceA->id)
        ->assertOk()
        ->assertSee('/deliveries/'.$deliveryA->id)
        ->assertDontSee('/deliveries/'.$deliveryB->id);
});

it('filters deliveries by destination', function () {
    $destination = Destination::factory()->create();
    $match = Delivery::factory()->create(['destination_id' => $destination->id]);
    $other = Delivery::factory()->create();

    $this->get('/deliveries?destination_id='.$destination->id)
        ->assertOk()
        ->assertSee('/deliveries/'.$match->id)
        ->assertDontSee('/deliveries/'.$other->id);
});

it('shows an explicit empty state for a filter that matches nothing', function () {
    Delivery::factory()->delivered()->create();

    $this->get('/deliveries?status=dead')->assertOk()->assertSee('No deliveries match these filters');
});
