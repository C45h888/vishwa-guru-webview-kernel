<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Adapters\PayPal;

use App\Shared\Contracts\ConfigurationContract;
use App\Shared\Exceptions\ConfigurationException;

/**
 * Factory for creating PayPalClient instances.
 * Reads configuration and validates credentials.
 */
final class PayPalClientFactory
{
    public function __construct(
        private ConfigurationContract $config,
    ) {}

    /**
     * Create a new PayPalClient with configuration credentials.
     *
     * @throws ConfigurationException if credentials are missing in non-local env
     */
    public function create(): PayPalClient
    {
        $clientId = (string) $this->config->get('payments.providers.paypal.client_id', '');
        $clientSecret = (string) $this->config->get('payments.providers.paypal.client_secret', '');
        $environment = (string) $this->config->get('payments.providers.paypal.environment', 'sandbox');
        $environment = $environment === 'live' ? 'live' : 'sandbox';

        if ($environment !== 'sandbox' && ($clientId === '' || $clientSecret === '')) {
            throw new ConfigurationException(
                "PayPal credentials are not configured for environment [{$environment}]. " .
                "Please set payments.providers.paypal.client_id and client_secret.",
            );
        }

        return new PayPalClient($clientId, $clientSecret, $environment === 'sandbox');
    }

    /**
     * Check if PayPal is configured and enabled.
     */
    public function isConfigured(): bool
    {
        $clientId = (string) $this->config->get('payments.providers.paypal.client_id', '');

        return $clientId !== '';
    }
}
