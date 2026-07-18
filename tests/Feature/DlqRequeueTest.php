<?php

use App\Jobs\DeliverEvent;
use App\Models\Delivery;
use App\Models\DeliveryAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Queue::fake();
});

it('lists only dead deliveries', function () {
    $dead = Delivery::factory()->dead()->create();
    $delivered = Delivery::factory()->delivered()->create();
    $pending = Delivery::factory()->create();

    $this->get('/dlq')
        ->assertOk()
        ->assertSee('/deliveries/'.$dead->id)
        ->assertDontSee('/deliveries/'.$delivered->id)
        ->assertDontSee('/deliveries/'.$pending->id);
});

it('shows an empty state when there are no dead deliveries', function () {
    $this->get('/dlq')->assertOk()->assertSee('No dead deliveries');
});

it('requeues a dead delivery to pending with a fresh job and preserved history', function () {
    $delivery = Delivery::factory()->dead()->create(['attempt_count' => 3]);
    DeliveryAttempt::factory()->count(3)->create(['delivery_id' => $delivery->id]);

    $this->post('/dlq/'.$delivery->id.'/requeue')
        ->assertRedirect('/dlq')
        ->assertSessionHas('status', 'Requeued 1 delivery.');

    $delivery->refresh();
    expect($delivery->status)->toBe(Delivery::STATUS_PENDING);
    expect($delivery->next_attempt_at)->toBeNull();
    expect($delivery->attempt_count)->toBe(3);
    expect($delivery->attempts()->count())->toBe(3);

    Queue::assertPushed(DeliverEvent::class, 1);
});

it('refuses to requeue a delivery that is not dead', function () {
    $delivery = Delivery::factory()->delivered()->create();

    $this->post('/dlq/'.$delivery->id.'/requeue')
        ->assertRedirect('/dlq')
        ->assertSessionHas('error');

    expect($delivery->fresh()->status)->toBe(Delivery::STATUS_DELIVERED);
    Queue::assertNothingPushed();
});

it('requeues every dead delivery in bulk', function () {
    Delivery::factory()->dead()->count(5)->create();
    Delivery::factory()->delivered()->create();

    $this->post('/dlq/requeue-all')
        ->assertRedirect('/dlq')
        ->assertSessionHas('status', 'Requeued 5 deliveries.');

    expect(Delivery::where('status', Delivery::STATUS_DEAD)->count())->toBe(0);
    expect(Delivery::where('status', Delivery::STATUS_PENDING)->count())->toBe(5);
    Queue::assertPushed(DeliverEvent::class, 5);
});
