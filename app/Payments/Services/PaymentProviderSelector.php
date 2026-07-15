<?php

declare(strict_types=1);

namespace App\Payments\Services;

use App\Payments\Contracts\PaymentGatewayContract;
use App\Payments\Domain\Enums\PaymentProvider;
use App\Payments\Domain\Exceptions\GatewaySelectionException;
use App\Payments\Domain\ValueObjects\PaymentIntent;

/**
 * Selects the best PaymentGatewayContract for a PaymentIntent.
 *
 * Selection is a deterministic, side-effect-free pipeline. The
 * selector does not call any gateway; it only consults the
 * PaymentGatewayContract capability surface that each adapter
 * already exposes (providerName / supports / enabled / min /
 * max / priority).
 *
 * Capability methods live on the PaymentGatewayContract (per-adapter)
 * rather than the PaymentProvider enum because capability varies by
 * deployment (e.g. Razorpay sandbox vs production key, PayPal
 * merchant cap). The enum is the canonical provider code carrier;
 * the adapter is the runtime capability source of truth.
 *
 * Selection pipeline (in order):
 *   1. Filter to gateways that report enabled() = true.
 *   2. Filter to gateways that support() the intent's currency.
 *   3. Filter to gateways whose [minimumAmount, maximumAmount]
 *      bound contains the intent's amount_minor.
 *   4. If the intent declares candidateProviders, restrict the pool
 *      to that ordered list (preserving the caller's preference).
 *   5. Sort the surviving pool by priority() ascending.
 *   6. Return the first element, or throw GatewaySelectionException
 *      carrying the pool state for diagnostics.
 *
 * Gateways are injected as iterable<PaymentGatewayContract>. The
 * container tags them by provider code (Pass 1.6 wires the tag).
 */
final class PaymentProviderSelector
{
    /**
     * @param  iterable<PaymentGatewayContract>  $gateways
     */
    public function __construct(
        private readonly iterable $gateways,
    ) {}

    /**
     * @throws GatewaySelectionException
     */
    public function select(PaymentIntent $intent): PaymentGatewayContract
    {
        $pool = $this->pool($intent);

        if ($pool === []) {
            throw GatewaySelectionException::noProviderForCurrency(
                $intent->currency(),
                $intent->candidateProviders(),
            );
        }

        return $pool[0]['gateway'];
    }

    /**
     * Read-only variant of select() that returns the entire surviving
     * pool. Used by diagnostics and the orchestrator's "fallback"
     * retry path. Never throws.
     *
     * @return array<int, array{gateway: PaymentGatewayContract, provider: PaymentProvider, priority: int}>
     */
    public function candidates(PaymentIntent $intent): array
    {
        return $this->pool($intent);
    }

    /**
     * @return array<int, array{gateway: PaymentGatewayContract, provider: PaymentProvider, priority: int}>
     */
    private function pool(PaymentIntent $intent): array
    {
        $candidates = $intent->candidateProviders();
        $pool = [];

        foreach ($this->gateways as $gateway) {
            if (! $gateway->enabled()) {
                continue;
            }
            if (! $gateway->supports($intent->currency())) {
                continue;
            }
            if ($intent->amountMinor() < $gateway->minimumAmount()) {
                continue;
            }
            if ($intent->amountMinor() > $gateway->maximumAmount()) {
                continue;
            }

            $provider = $this->providerFromCode($gateway->providerName());
            if ($provider === null) {
                continue;
            }

            if ($candidates !== [] && ! in_array($provider, $candidates, true)) {
                continue;
            }

            $pool[] = [
                'gateway' => $gateway,
                'provider' => $provider,
                'priority' => $gateway->priority(),
            ];
        }

        usort(
            $pool,
            static fn (array $a, array $b): int => $a['priority'] <=> $b['priority'],
        );

        return $pool;
    }

    private function providerFromCode(string $code): ?PaymentProvider
    {
        foreach (PaymentProvider::cases() as $case) {
            if ($case->value === $code) {
                return $case;
            }
        }

        return null;
    }
}