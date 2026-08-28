<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies;

class TrustCityCareProxies extends TrustProxies
{
    protected function proxies(): array|string|null
    {
        $configured = config('citycare.trusted_proxies', []);

        return $configured === [] ? parent::proxies() : $configured;
    }
}
