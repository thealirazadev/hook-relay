<?php

use App\Models\Delivery;
use App\Models\Source;
use App\Models\User;
use App\Models\WebhookEvent;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('filters events by source', function () {
    $a = Source::factory()->create();
    $b = Source::factory()->create();
    $eventA = WebhookEvent::factory()->for($a)->create(['provider_event_id' => 'from-a']);
    $eventB = WebhookEvent::factory()->for($b)->create(['provider_event_id' => 'from-b']);

    $this->get('/events?source_id='.$a->id)
        ->assertOk()
        ->assertSee('from-a')
        ->assertDontSee('from-b');
});

it('filters events by type', function () {
    WebhookEvent::factory()->create(['event_type' => 'charge.succeeded', 'provider_event_id' => 'keep']);
    WebhookEvent::factory()->create(['event_type' => 'charge.failed', 'provider_event_id' => 'drop']);

    $this->get('/events?event_type=charge.succeeded')
        ->assertOk()
        ->assertSee('keep')
        ->assertDontSee('drop');
});

it('searches by provider event id', function () {
    WebhookEvent::factory()->create(['provider_event_id' => 'evt_abc123']);
    WebhookEvent::factory()->create(['provider_event_id' => 'evt_zzz999']);

    $this->get('/events?q=abc123')->assertOk()->assertSee('evt_abc123')->assertDontSee('evt_zzz999');
    $this->get('/events?q=nomatch')->assertOk()->assertSee('No events match');
});

it('filters events by date range', function () {
    $old = WebhookEvent::factory()->create(['received_at' => now()->subDays(10), 'provider_event_id' => 'old-one']);
    $recent = WebhookEvent::factory()->create(['received_at' => now()->subDay(), 'provider_event_id' => 'recent-one']);

    $this->get('/events?from='.now()->subDays(3)->toDateString())
        ->assertOk()
        ->assertSee('recent-one')
        ->assertDontSee('old-one');
});

it('ignores nonsense filter values without erroring', function () {
    WebhookEvent::factory()->create(['provider_event_id' => 'present']);

    $this->get('/events?source_id=abc&from=notadate')
        ->assertOk()
        ->assertSee('present');
});

it('combines filters and keeps them across pagination links', function () {
    $source = Source::factory()->create();
    WebhookEvent::factory()->count(30)->for($source)->create(['event_type' => 'ping']);
    WebhookEvent::factory()->create(['event_type' => 'other']);

    $response = $this->get('/events?source_id='.$source->id.'&event_type=ping');
    $response->assertOk()->assertSee('source_id='.$source->id);
});

it('shows event detail with payload headers and deliveries', function () {
    $event = WebhookEvent::factory()->create([
        'payload' => '{"amount":100}',
        'headers' => ['x-github-event' => 'push'],
    ]);
    $delivery = Delivery::factory()->for($event, 'event')->create();

    $this->get('/events/'.$event->id)
        ->assertOk()
        ->assertSee('{"amount":100}')
        ->assertSee('x-github-event')
        ->assertSee(substr($delivery->id, 0, 12));
});

it('escapes payload content to prevent stored xss', function () {
    $event = WebhookEvent::factory()->create(['payload' => '<script>alert(1)</script>']);

    $this->get('/events/'.$event->id)
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;', false);
});
