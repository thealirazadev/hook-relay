<?php

use App\Models\Destination;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('generates a signing secret on create and stores it encrypted', function () {
    $this->post('/destinations', [
        'name' => 'Signed consumer',
        'url' => 'https://consumer.test/webhooks',
        'active' => '1',
    ])->assertRedirect('/destinations');

    $destination = Destination::sole();
    expect($destination->signing_secret)->toStartWith('whsec_');

    $raw = DB::table('destinations')->where('id', $destination->id)->value('signing_secret');
    expect($raw)->not->toBe($destination->signing_secret)
        ->and($raw)->not->toContain('whsec_');
});

it('lists destinations', function () {
    Destination::factory()->create(['name' => 'Internal consumer', 'url' => 'https://example.test/hook']);

    $this->get('/destinations')
        ->assertOk()
        ->assertSee('Internal consumer')
        ->assertSee('https://example.test/hook');
});

it('shows an empty state when there are no destinations', function () {
    $this->get('/destinations')->assertOk()->assertSee('No destinations yet');
});

it('creates a destination', function () {
    $this->post('/destinations', [
        'name' => 'Consumer',
        'url' => 'https://consumer.test/webhooks',
        'active' => '1',
    ])->assertRedirect('/destinations');

    $destination = Destination::sole();
    expect($destination->name)->toBe('Consumer');
    expect($destination->active)->toBeTrue();
});

it('rejects a non-http url', function () {
    $this->from('/destinations/create')->post('/destinations', [
        'name' => 'Bad',
        'url' => 'ftp://consumer.test/webhooks',
    ])->assertRedirect('/destinations/create')->assertSessionHasErrors('url');

    expect(Destination::count())->toBe(0);
});

it('rejects a missing name and url', function () {
    $this->from('/destinations/create')->post('/destinations', [])
        ->assertSessionHasErrors(['name', 'url']);

    expect(Destination::count())->toBe(0);
});

it('updates a destination', function () {
    $destination = Destination::factory()->create();

    $this->put('/destinations/'.$destination->id, [
        'name' => 'Renamed',
        'url' => 'https://new.test/hook',
        'active' => '0',
    ])->assertRedirect('/destinations');

    $destination->refresh();
    expect($destination->name)->toBe('Renamed');
    expect($destination->url)->toBe('https://new.test/hook');
    expect($destination->active)->toBeFalse();
});

it('soft deletes a destination', function () {
    $destination = Destination::factory()->create();

    $this->delete('/destinations/'.$destination->id)->assertRedirect('/destinations');

    expect(Destination::count())->toBe(0);
    expect(Destination::withTrashed()->count())->toBe(1);
});

it('reveals the current signing secret on the edit screen', function () {
    $destination = Destination::factory()->create(['signing_secret' => 'whsec_visible']);

    $this->get('/destinations/'.$destination->id.'/edit')
        ->assertOk()
        ->assertSee('whsec_visible');
});

it('rotates the signing secret and reveals the new one once', function () {
    $destination = Destination::factory()->create();
    $old = $destination->signing_secret;

    $this->post('/destinations/'.$destination->id.'/rotate-secret')
        ->assertRedirect('/destinations/'.$destination->id.'/edit')
        ->assertSessionHas('revealed_secret');

    $new = $destination->fresh()->signing_secret;
    expect($new)->not->toBe($old)
        ->and($new)->toStartWith('whsec_');
});

it('requires authentication to rotate a signing secret', function () {
    auth()->logout();
    $destination = Destination::factory()->create();

    $this->post('/destinations/'.$destination->id.'/rotate-secret')
        ->assertRedirect('/login');
});
