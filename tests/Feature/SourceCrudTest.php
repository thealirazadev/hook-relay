<?php

use App\Models\Source;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('lists sources with their ingest url', function () {
    $source = Source::factory()->create(['name' => 'Stripe production']);

    $this->get('/sources')
        ->assertOk()
        ->assertSee('Stripe production')
        ->assertSee($source->ingest_key);
});

it('shows an empty state when there are no sources', function () {
    $this->get('/sources')
        ->assertOk()
        ->assertSee('No sources yet');
});

it('creates a source and shows the full ingest url', function () {
    $this->post('/sources', [
        'name' => 'My Stripe',
        'provider' => 'stripe',
        'signing_secret' => 'whsec_live_123',
    ])->assertRedirect('/sources');

    $source = Source::firstWhere('name', 'My Stripe');
    expect($source)->not->toBeNull();
    expect($source->provider)->toBe('stripe');
    expect(strlen($source->ingest_key))->toBe(32);

    $this->get('/sources')->assertSee(rtrim(config('app.url'), '/').'/ingest/'.$source->ingest_key);
});

it('stores the signing secret encrypted and never renders it back', function () {
    $this->post('/sources', [
        'name' => 'Secretful',
        'provider' => 'generic',
        'signing_secret' => 'super-secret-value',
    ]);

    $raw = DB::table('sources')->where('name', 'Secretful')->value('signing_secret');
    expect($raw)->not->toContain('super-secret-value');

    $source = Source::firstWhere('name', 'Secretful');
    expect($source->signing_secret)->toBe('super-secret-value');

    $this->get('/sources/'.$source->id.'/edit')
        ->assertOk()
        ->assertDontSee('super-secret-value');
});

it('trims trailing whitespace from the secret on save', function () {
    $this->post('/sources', [
        'name' => 'Trimmed',
        'provider' => 'generic',
        'signing_secret' => '  padded-secret  ',
    ]);

    expect(Source::firstWhere('name', 'Trimmed')->signing_secret)->toBe('padded-secret');
});

it('validates the create form', function () {
    $this->from('/sources/create')->post('/sources', [
        'name' => '',
        'provider' => 'unknown',
        'signing_secret' => '',
    ])->assertRedirect('/sources/create')
        ->assertSessionHasErrors(['name', 'provider', 'signing_secret']);

    expect(Source::count())->toBe(0);
});

it('updates the name and keeps the secret when left blank', function () {
    $source = Source::factory()->create(['name' => 'Old', 'signing_secret' => 'keep-me']);

    $this->put('/sources/'.$source->id, [
        'name' => 'New name',
        'signing_secret' => '',
        'active' => '1',
    ])->assertRedirect('/sources');

    $source->refresh();
    expect($source->name)->toBe('New name');
    expect($source->signing_secret)->toBe('keep-me');
});

it('replaces the secret when a new one is supplied', function () {
    $source = Source::factory()->create(['signing_secret' => 'old-secret']);

    $this->put('/sources/'.$source->id, [
        'name' => $source->name,
        'signing_secret' => 'new-secret',
        'active' => '1',
    ]);

    expect($source->refresh()->signing_secret)->toBe('new-secret');
});

it('soft deletes a source and keeps it out of default queries', function () {
    $source = Source::factory()->create();

    $this->delete('/sources/'.$source->id)->assertRedirect('/sources');

    expect(Source::count())->toBe(0);
    expect(Source::withTrashed()->count())->toBe(1);
});
