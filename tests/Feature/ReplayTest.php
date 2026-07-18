<?php

use App\Jobs\DeliverEvent;
use App\Models\Delivery;
use App\Models\Destination;
use App\Models\Source;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Queue::fake();
});

it('replays an event to currently routed destinations without a duplicate event row', function () {
    $source = Source::factory()->create();
    $destination = Destination::factory()->create();
    $source->destinations()->sync([$destination->id]);
    $event = WebhookEvent::factory()->for($source)->create();

    $this->post('/events/'.$event->id.'/replay')->assertRedirect('/events/'.$event->id);

    expect(WebhookEvent::count())->toBe(1);
    expect(Delivery::where('webhook_event_id', $event->id)->count())->toBe(1);
    Queue::assertPushed(DeliverEvent::class, 1);
});

it('reuses the same event id so downstream dedupe still works', function () {
    $source = Source::factory()->create();
    $destination = Destination::factory()->create();
    $source->destinations()->sync([$destination->id]);
    $event = WebhookEvent::factory()->for($source)->create();

    $this->post('/events/'.$event->id.'/replay');

    $delivery = Delivery::where('webhook_event_id', $event->id)->first();
    expect($delivery->webhook_event_id)->toBe($event->id);
});

it('replays only to active routed destinations', function () {
    $source = Source::factory()->create();
    $active = Destination::factory()->create();
    $inactive = Destination::factory()->inactive()->create();
    $source->destinations()->sync([$active->id, $inactive->id]);
    $event = WebhookEvent::factory()->for($source)->create();

    $this->post('/events/'.$event->id.'/replay');

    expect(Delivery::where('webhook_event_id', $event->id)->count())->toBe(1);
    expect(Delivery::sole()->destination_id)->toBe($active->id);
});

it('gives a friendly message when the source has no routed destinations', function () {
    $source = Source::factory()->create();
    $event = WebhookEvent::factory()->for($source)->create();

    $this->post('/events/'.$event->id.'/replay')
        ->assertRedirect('/events/'.$event->id)
        ->assertSessionHas('status', fn ($message) => str_contains($message, 'no active routed destinations'));

    expect(Delivery::count())->toBe(0);
    Queue::assertNothingPushed();
});
