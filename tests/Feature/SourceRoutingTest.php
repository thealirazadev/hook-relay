<?php

use App\Models\Destination;
use App\Models\Source;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('shows destination checkboxes on the source edit screen', function () {
    $source = Source::factory()->create();
    $destination = Destination::factory()->create(['name' => 'Routable']);

    $this->get('/sources/'.$source->id.'/edit')
        ->assertOk()
        ->assertSee('Routed destinations')
        ->assertSee('Routable');
});

it('attaches selected destinations to a source', function () {
    $source = Source::factory()->create();
    $a = Destination::factory()->create();
    $b = Destination::factory()->create();

    $this->put('/sources/'.$source->id, [
        'name' => $source->name,
        'active' => '1',
        'destination_ids' => [$a->id, $b->id],
    ])->assertRedirect('/sources');

    expect($source->destinations()->pluck('destinations.id')->all())->toEqualCanonicalizing([$a->id, $b->id]);
});

it('detaching a destination removes the routing', function () {
    $source = Source::factory()->create();
    $a = Destination::factory()->create();
    $b = Destination::factory()->create();
    $source->destinations()->sync([$a->id, $b->id]);

    $this->put('/sources/'.$source->id, [
        'name' => $source->name,
        'active' => '1',
        'destination_ids' => [$a->id],
    ]);

    expect($source->destinations()->pluck('destinations.id')->all())->toBe([$a->id]);
});

it('clears all routing when no destinations are selected', function () {
    $source = Source::factory()->create();
    $a = Destination::factory()->create();
    $source->destinations()->sync([$a->id]);

    $this->put('/sources/'.$source->id, [
        'name' => $source->name,
        'active' => '1',
    ]);

    expect($source->destinations()->count())->toBe(0);
});

it('rejects routing to a destination that does not exist', function () {
    $source = Source::factory()->create();

    $this->from('/sources/'.$source->id.'/edit')->put('/sources/'.$source->id, [
        'name' => $source->name,
        'active' => '1',
        'destination_ids' => [9999],
    ])->assertSessionHasErrors('destination_ids.0');
});
