<?php

use App\Support\Providers\GenericProvider;
use App\Support\Providers\GithubProvider;
use App\Support\Providers\ProviderResolver;
use App\Support\Providers\ShopifyProvider;
use App\Support\Providers\StripeProvider;

beforeEach(function () {
    $this->resolver = new ProviderResolver;
});

it('resolves each provider to its verifier', function () {
    expect($this->resolver->for('stripe'))->toBeInstanceOf(StripeProvider::class);
    expect($this->resolver->for('github'))->toBeInstanceOf(GithubProvider::class);
    expect($this->resolver->for('shopify'))->toBeInstanceOf(ShopifyProvider::class);
    expect($this->resolver->for('generic'))->toBeInstanceOf(GenericProvider::class);
});

it('throws for an unknown provider', function () {
    $this->resolver->for('paypal');
})->throws(InvalidArgumentException::class);
