<?php

use App\Jobs\DeliverEvent;
use App\Models\Delivery;
use App\Models\Destination;
use App\Models\Source;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Queue::fake();
});

function ingestGeneric(Source $source, string $body, array $extra = []): TestResponse
{
    return postIngest($source->ingest_key, $body, array_merge([
        'X-Signature' => genericSignature($body, 'gen'),
    ], $extra));
}

it('creates one pending delivery per active routed destination', function () {
    $source = Source::factory()->provider('generic')->create(['signing_secret' => 'gen']);
    $a = Destination::factory()->create();
    $b = Destination::factory()->create();
    $source->destinations()->sync([$a->id, $b->id]);

    ingestGeneric($source, '{"n":1}')->assertOk();

    expect(Delivery::count())->toBe(2);
    expect(Delivery::where('status', Delivery::STATUS_PENDING)->count())->toBe(2);
    expect(Delivery::pluck('destination_id')->all())->toEqualCanonicalizing([$a->id, $b->id]);
    Queue::assertPushed(DeliverEvent::class, 2);
});

it('excludes inactive and unrouted destinations', function () {
    $source = Source::factory()->provider('generic')->create(['signing_secret' => 'gen']);
    $routedActive = Destination::factory()->create();
    $routedInactive = Destination::factory()->inactive()->create();
    Destination::factory()->create(); // not routed
    $source->destinations()->sync([$routedActive->id, $routedInactive->id]);

    ingestGeneric($source, '{"n":1}')->assertOk();

    expect(Delivery::count())->toBe(1);
    expect(Delivery::sole()->destination_id)->toBe($routedActive->id);
});

it('accepts an event with zero deliveries when the source has no routes', function () {
    $source = Source::factory()->provider('generic')->create(['signing_secret' => 'gen']);

    ingestGeneric($source, '{"n":1}')->assertOk();

    expect(Delivery::count())->toBe(0);
});

it('snapshots the attempt cap onto each delivery', function () {
    config()->set('hook_relay.delivery_max_attempts', 5);
    $source = Source::factory()->provider('generic')->create(['signing_secret' => 'gen']);
    $source->destinations()->sync([Destination::factory()->create()->id]);

    ingestGeneric($source, '{"n":1}')->assertOk();

    expect(Delivery::sole()->max_attempts)->toBe(5);
});

it('does not create new deliveries for a duplicate event', function () {
    $source = Source::factory()->provider('generic')->create(['signing_secret' => 'gen']);
    $source->destinations()->sync([Destination::factory()->create()->id]);

    ingestGeneric($source, '{"n":1}', ['X-Event-Id' => 'e1'])->assertOk();
    ingestGeneric($source, '{"n":1}', ['X-Event-Id' => 'e1'])->assertJson(['data' => ['duplicate' => true]]);

    expect(Delivery::count())->toBe(1);
});
