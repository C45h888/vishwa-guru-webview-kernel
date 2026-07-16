<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Adapters\Razorpay;

use App\Shared\Contracts\ConfigurationContract;
use App\Shared\Exceptions\ConfigurationException;

/**
 * Factory for creating RazorpayClient instances.
 * Reads configuration and validates credentials.
 */
final class RazorpayClientFactory
{
    public function __construct(
        private ConfigurationContract $config,
    ) {}

    /**
     * Create a new RazorpayClient with configuration credentials.
     *
     * @throws ConfigurationException if credentials are missing in non-local env
     */
    public function create(): RazorpayClient
    {
        $keyId = (string) $this->config->get('payments.providers.razorpay.key_id', '');
        $keySecret = (string) $this->config->get('payments.providers.razorpay.key_secret', '');
        $environment = (string) $this->config->get('payments.providers.razorpay.environment', 'local');

        if ($environment !== 'local' && ($keyId === '' || $keySecret === '')) {
            throw new ConfigurationException(
                "Razorpay credentials are not configured for environment [{$environment}]. " .
                "Please set payments.providers.razorpay.key_id and key_secret.",
            );
        }

        return new RazorpayClient($keyId, $keySecret);
    }

    /**
     * Check if Razorpay is configured and enabled.
     */
    public function isConfigured(): bool
    {
        $keyId = (string) $this->config->get('payments.providers.razorpay.key_id', '');

        return $keyId !== '';
    }
}
