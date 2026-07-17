<?php

use App\Models\Delivery;
use App\Models\DeliveryAttempt;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('shows the delivery detail with its attempt history newest first', function () {
    $delivery = Delivery::factory()->status(Delivery::STATUS_FAILED)->create(['attempt_count' => 2]);
    DeliveryAttempt::factory()->create(['delivery_id' => $delivery->id, 'attempt_number' => 1, 'response_status' => 500]);
    DeliveryAttempt::factory()->create(['delivery_id' => $delivery->id, 'attempt_number' => 2, 'response_status' => 503]);

    $this->get('/deliveries/'.$delivery->id)
        ->assertOk()
        ->assertSee($delivery->id)
        ->assertSee('Attempt history')
        ->assertSee('500')
        ->assertSee('503');
});

it('shows a connection error on a failed attempt', function () {
    $delivery = Delivery::factory()->status(Delivery::STATUS_DEAD)->create();
    DeliveryAttempt::factory()->connectionError('Connection refused')->create(['delivery_id' => $delivery->id]);

    $this->get('/deliveries/'.$delivery->id)
        ->assertOk()
        ->assertSee('Connection refused');
});
