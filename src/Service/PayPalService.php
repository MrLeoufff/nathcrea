<?php

namespace App\Service;

use PayPalCheckoutSdk\Core\LiveEnvironment;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;

class PayPalService
{
    private PayPalHttpClient $client;

    public function __construct(string $clientId, string $clientSecret, string $mode)
    {
        $environment = $mode === 'live'
        ? new LiveEnvironment($clientId, $clientSecret)
        : new SandboxEnvironment($clientId, $clientSecret);

        $this->client = new PayPalHttpClient($environment);
    }

    public function getClient(): PayPalHttpClient
    {
        return $this->client;
    }
}
