<?php

use App\Models\Delivery;
use App\Models\DeliveryAttempt;
use App\Models\WebhookEvent;

function prune(): void
{
    test()->artisan('model:prune', ['--model' => [WebhookEvent::class]])->assertSuccessful();
}

it('prunes terminal events older than the retention window', function () {
    config()->set('hook_relay.retention_days', 30);
    $event = WebhookEvent::factory()->create(['received_at' => now()->subDays(31)]);
    Delivery::factory()->delivered()->for($event, 'event')->create();

    prune();

    expect(WebhookEvent::whereKey($event->id)->exists())->toBeFalse();
});

it('cascades the delete to deliveries and attempts', function () {
    config()->set('hook_relay.retention_days', 30);
    $event = WebhookEvent::factory()->create(['received_at' => now()->subDays(60)]);
    $delivery = Delivery::factory()->delivered()->for($event, 'event')->create();
    DeliveryAttempt::factory()->count(2)->create(['delivery_id' => $delivery->id]);

    prune();

    expect(Delivery::whereKey($delivery->id)->exists())->toBeFalse();
    expect(DeliveryAttempt::where('delivery_id', $delivery->id)->exists())->toBeFalse();
});

it('keeps events newer than the retention window', function () {
    config()->set('hook_relay.retention_days', 30);
    $event = WebhookEvent::factory()->create(['received_at' => now()->subDays(10)]);
    Delivery::factory()->delivered()->for($event, 'event')->create();

    prune();

    expect(WebhookEvent::whereKey($event->id)->exists())->toBeTrue();
});

it('keeps old events that still have a non-terminal delivery', function () {
    config()->set('hook_relay.retention_days', 30);
    $event = WebhookEvent::factory()->create(['received_at' => now()->subDays(90)]);
    Delivery::factory()->status(Delivery::STATUS_FAILED)->for($event, 'event')->create();

    prune();

    expect(WebhookEvent::whereKey($event->id)->exists())->toBeTrue();
});

it('prunes dead-only events but keeps failed-delivery events', function () {
    config()->set('hook_relay.retention_days', 0);
    $deadOnly = WebhookEvent::factory()->create(['received_at' => now()->subDay()]);
    Delivery::factory()->dead()->for($deadOnly, 'event')->create();

    $withFailed = WebhookEvent::factory()->create(['received_at' => now()->subDay()]);
    Delivery::factory()->status(Delivery::STATUS_FAILED)->for($withFailed, 'event')->create();

    prune();

    expect(WebhookEvent::whereKey($deadOnly->id)->exists())->toBeFalse();
    expect(WebhookEvent::whereKey($withFailed->id)->exists())->toBeTrue();
});
