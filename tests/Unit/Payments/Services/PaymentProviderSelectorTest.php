<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Services;

use App\Payments\Contracts\PaymentGatewayContract;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Enums\PaymentProvider;
use App\Payments\Domain\Exceptions\GatewaySelectionException;
use App\Payments\Domain\ValueObjects\DonorIdentity;
use App\Payments\Domain\ValueObjects\PaymentIntent;
use App\Payments\Services\PaymentProviderSelector;
use App\Shared\Support\Result;
use App\Shared\ValueObjects\Identifier;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the PaymentProviderSelector.
 *
 * The selector is pure (no I/O, no time, no randomness) so each test
 * constructs stub PaymentGatewayContract instances and asserts the
 * 6-rule pipeline outcome directly.
 */
final class PaymentProviderSelectorTest extends TestCase
{
    public function testSelectsTheHighestPriorityEnabledGateway(): void
    {
        $razorpay = $this->stubGateway(
            code: 'razorpay',
            enabled: true,
            currencies: [Currency::INR],
            minAmount: 100,
            maxAmount: 15_000_000,
            priority: 10,
        );
        $paypal = $this->stubGateway(
            code: 'paypal',
            enabled: true,
            currencies: [Currency::INR],
            minAmount: 1,
            maxAmount: 1_000_000,
            priority: 20,
        );

        $selector = new PaymentProviderSelector([$razorpay, $paypal]);

        $intent = $this->intent(Currency::INR, 5000);
        $selected = $selector->select($intent);

        $this->assertSame('razorpay', $selected->providerName());
    }

    public function testFallsBackToSecondPriorityWhenFirstIsDisabled(): void
    {
        $razorpay = $this->stubGateway(
            code: 'razorpay',
            enabled: false,
            currencies: [Currency::INR],
            minAmount: 100,
            maxAmount: 15_000_000,
            priority: 10,
        );
        $paypal = $this->stubGateway(
            code: 'paypal',
            enabled: true,
            currencies: [Currency::INR],
            minAmount: 1,
            maxAmount: 1_000_000,
            priority: 20,
        );

        $selector = new PaymentProviderSelector([$razorpay, $paypal]);

        $selected = $selector->select($this->intent(Currency::INR, 5000));

        $this->assertSame('paypal', $selected->providerName());
    }

    public function testFiltersOutGatewaysThatDoNotSupportTheCurrency(): void
    {
        $razorpay = $this->stubGateway(
            code: 'razorpay',
            enabled: true,
            currencies: [Currency::INR],
            minAmount: 100,
            maxAmount: 15_000_000,
            priority: 10,
        );
        $paypal = $this->stubGateway(
            code: 'paypal',
            enabled: true,
            currencies: [Currency::USD],
            minAmount: 1,
            maxAmount: 1_000_000,
            priority: 20,
        );

        $selector = new PaymentProviderSelector([$razorpay, $paypal]);

        $selected = $selector->select($this->intent(Currency::USD, 500));

        $this->assertSame('paypal', $selected->providerName());
    }

    public function testFiltersOutGatewaysWhoseAmountIsBelowTheirMinimum(): void
    {
        $razorpay = $this->stubGateway(
            code: 'razorpay',
            enabled: true,
            currencies: [Currency::INR],
            minAmount: 100_000, // ₹1000 floor
            maxAmount: 15_000_000,
            priority: 10,
        );

        $selector = new PaymentProviderSelector([$razorpay]);

        $this->expectException(GatewaySelectionException::class);
        $selector->select($this->intent(Currency::INR, 50_000));
    }

    public function testFiltersOutGatewaysWhoseAmountExceedsTheirMaximum(): void
    {
        $razorpay = $this->stubGateway(
            code: 'razorpay',
            enabled: true,
            currencies: [Currency::INR],
            minAmount: 100,
            maxAmount: 100_000,
            priority: 10,
        );

        $selector = new PaymentProviderSelector([$razorpay]);

        $this->expectException(GatewaySelectionException::class);
        $selector->select($this->intent(Currency::INR, 200_000));
    }

    public function testCandidateProvidersListRestrictsThePool(): void
    {
        $razorpay = $this->stubGateway(
            code: 'razorpay',
            enabled: true,
            currencies: [Currency::INR],
            minAmount: 100,
            maxAmount: 15_000_000,
            priority: 10,
        );
        $paypal = $this->stubGateway(
            code: 'paypal',
            enabled: true,
            currencies: [Currency::INR],
            minAmount: 1,
            maxAmount: 1_000_000,
            priority: 20,
        );

        $selector = new PaymentProviderSelector([$razorpay, $paypal]);

        $intent = new PaymentIntent(
            donationId: new Identifier($this->ulid()),
            donor: new DonorIdentity(),
            amountMinor: 5000,
            currency: Currency::INR,
            purpose: 'donation',
            idempotencyKey: 'k1',
            candidateProviders: [PaymentProvider::PAYPAL],
        );

        $selected = $selector->select($intent);

        $this->assertSame('paypal', $selected->providerName());
    }

    public function testSortsPoolByPriorityAscending(): void
    {
        // Two distinct real provider codes (RAZORPAY + PAYPAL) with
        // Razorpay carrying lower priority — verifies the selector
        // emits the right ordering when multiple providers qualify.
        $razorpay = $this->stubGateway('razorpay', true, [Currency::INR], 1, 1_000_000, 10);
        $paypal = $this->stubGateway('paypal', true, [Currency::INR], 1, 1_000_000, 20);

        // Register in non-priority order to ensure the selector sorts.
        $selector = new PaymentProviderSelector([$paypal, $razorpay]);

        $candidates = $selector->candidates($this->intent(Currency::INR, 1000));

        $this->assertCount(2, $candidates);
        $this->assertSame('razorpay', $candidates[0]['provider']->value);
        $this->assertSame('paypal', $candidates[1]['provider']->value);
        $this->assertSame(10, $candidates[0]['priority']);
        $this->assertSame(20, $candidates[1]['priority']);
    }

    public function testThrowsGatewaySelectionExceptionWhenPoolIsEmpty(): void
    {
        $selector = new PaymentProviderSelector([]);

        $this->expectException(GatewaySelectionException::class);
        $selector->select($this->intent(Currency::INR, 1000));
    }

    private function intent(Currency $currency, int $amountMinor): PaymentIntent
    {
        return new PaymentIntent(
            donationId: new Identifier($this->ulid()),
            donor: new DonorIdentity(),
            amountMinor: $amountMinor,
            currency: $currency,
            purpose: 'donation',
            idempotencyKey: 'k',
        );
    }

    /**
     * @param  array<int, Currency>  $currencies
     */
    private function stubGateway(
        string $code,
        bool $enabled,
        array $currencies,
        int $minAmount,
        int $maxAmount,
        int $priority,
    ): PaymentGatewayContract {
        return new class($code, $enabled, $currencies, $minAmount, $maxAmount, $priority) implements PaymentGatewayContract {
            /**
             * @param  array<int, Currency>  $currencies
             */
            public function __construct(
                private readonly string $code,
                private readonly bool $enabledFlag,
                private readonly array $supportedCurrencies,
                private readonly int $min,
                private readonly int $max,
                private readonly int $prio,
            ) {}

            public function providerName(): string
            {
                return $this->code;
            }

            public function providerCode(): string
            {
                return $this->code;
            }

            public function supports(Currency $currency): bool
            {
                return in_array($currency, $this->supportedCurrencies, true);
            }

            public function enabled(): bool
            {
                return $this->enabledFlag;
            }

            public function minimumAmount(): int
            {
                return $this->min;
            }

            public function maximumAmount(): int
            {
                return $this->max;
            }

            public function priority(): int
            {
                return $this->prio;
            }

            public function initialize(\App\Payments\Domain\ValueObjects\PaymentRequest $request): Result
            {
                return Result::success(['order_id' => 'order_'.$this->code]);
            }

            public function verify(string $gatewayOrderId, ?string $gatewayPaymentId = null): Result
            {
                return Result::success(\App\Payments\Domain\Enums\TransactionStatus::CAPTURED);
            }

            public function capture(\App\Shared\ValueObjects\Identifier $transactionId, int $amount): Result
            {
                return Result::success(\App\Payments\Domain\Enums\TransactionStatus::CAPTURED);
            }

            public function refund(\App\Shared\ValueObjects\Identifier $transactionId, int $amount): Result
            {
                return Result::success(\App\Payments\Domain\Enums\TransactionStatus::REFUNDED);
            }
        };
    }

    private function ulid(): string
    {
        return \App\Shared\Support\UlidGenerator::generate();
    }
}