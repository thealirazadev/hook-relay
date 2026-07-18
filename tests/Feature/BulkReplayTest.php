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

function routedEvent(): WebhookEvent
{
    $source = Source::factory()->create();
    $destination = Destination::factory()->create();
    $source->destinations()->sync([$destination->id]);

    return WebhookEvent::factory()->for($source)->create();
}

it('bulk replays selected events and creates deliveries for all of them', function () {
    $events = collect(range(1, 3))->map(fn () => routedEvent());

    $this->post('/events/replay', ['event_ids' => $events->pluck('id')->all()])
        ->assertRedirect('/events');

    expect(Delivery::count())->toBe(3);
    expect(WebhookEvent::count())->toBe(3);
    Queue::assertPushed(DeliverEvent::class, 3);
});

it('rejects a bulk replay with no selection', function () {
    $this->from('/events')->post('/events/replay', [])
        ->assertRedirect('/events')
        ->assertSessionHasErrors('event_ids');

    expect(Delivery::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('rejects a bulk replay of more than 100 events', function () {
    $ids = WebhookEvent::factory()->count(101)->create()->pluck('id')->all();

    $this->from('/events')->post('/events/replay', ['event_ids' => $ids])
        ->assertRedirect('/events')
        ->assertSessionHasErrors('event_ids');

    expect(Delivery::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('rejects a bulk replay referencing an unknown event id', function () {
    $this->from('/events')->post('/events/replay', ['event_ids' => ['01000000000000000000000000']])
        ->assertSessionHasErrors('event_ids.0');

    Queue::assertNothingPushed();
});

it('does not create duplicate event rows on bulk replay', function () {
    $events = collect(range(1, 2))->map(fn () => routedEvent());

    $this->post('/events/replay', ['event_ids' => $events->pluck('id')->all()]);

    expect(WebhookEvent::count())->toBe(2);
});
