<?php

use App\Models\Source;

it('rate limits ingest per key and returns the envelope', function () {
    $source = Source::factory()->provider('generic')->create(['signing_secret' => 'gen']);
    $body = '{"a":1}';
    $headers = ['X-Signature' => genericSignature($body, 'gen')];

    foreach (range(1, 60) as $ignored) {
        postIngest($source->ingest_key, $body, $headers)->assertOk();
    }

    postIngest($source->ingest_key, $body, $headers)
        ->assertStatus(429)
        ->assertJson(['error' => ['code' => 'rate_limited']]);
});

it('does not let one key throttle another', function () {
    $a = Source::factory()->provider('generic')->create(['signing_secret' => 'gen']);
    $b = Source::factory()->provider('generic')->create(['signing_secret' => 'gen']);
    $body = '{"a":1}';

    foreach (range(1, 60) as $ignored) {
        postIngest($a->ingest_key, $body, ['X-Signature' => genericSignature($body, 'gen')]);
    }

    postIngest($a->ingest_key, $body, ['X-Signature' => genericSignature($body, 'gen')])->assertStatus(429);
    postIngest($b->ingest_key, $body, ['X-Signature' => genericSignature($body, 'gen')])->assertOk();
});
