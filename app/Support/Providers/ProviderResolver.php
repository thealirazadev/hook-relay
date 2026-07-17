<?php

namespace App\Support\Providers;

use InvalidArgumentException;

class ProviderResolver
{
    public function for(string $provider): Provider
    {
        return match ($provider) {
            'stripe' => new StripeProvider,
            'github' => new GithubProvider,
            'shopify' => new ShopifyProvider,
            'generic' => new GenericProvider,
            default => throw new InvalidArgumentException("Unknown provider [{$provider}]."),
        };
    }
}
