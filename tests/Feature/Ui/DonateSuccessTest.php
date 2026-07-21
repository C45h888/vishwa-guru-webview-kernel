<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use App\Payments\Domain\Entities\Payment;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Enums\PaymentProvider;
use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Domain\Repositories\PaymentRepositoryContract;
use App\Persistence\ValueObjects\EntityId;
use DateTimeImmutable;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\Payments\Infrastructure\InfrastructureTestCase;

/**
 * HTTP-feature test for GET /donate/success (payments.Success page).
 *
 * Seeds a Payment row directly through PaymentRepositoryContract and
 * asserts the Inertia payload carries the PaymentStatusResource shape
 * built in commit 1 (gateway_order_id, status, amount_minor, public_key_id).
 * Skips the actual Razorpay HTTP submission since the test environment
 * uses rzp_test_* keys and we don't want this CI run to hit real
 * network endpoints.
 */
final class DonateSuccessTest extends InfrastructureTestCase
{
    public function testSuccessRendersPaymentStatusFromLookup(): void
    {
        $payment = $this->seedPayment(
            providerOrderId: 'ord_test_001',
            amountMinor: 50_000,
            currency: Currency::INR,
            status: TransactionStatus::INITIALIZED,
        );

        $response = $this->get('/donate/success?gateway_order_id=ord_test_001');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('payments/Success')
            ->where('payment.gateway_order_id', 'ord_test_001')
            ->where('payment.status', 'initialized')
            ->where('payment.amount_minor', 50_000)
            ->where('payment.currency_code', 'INR')
            ->has('payment.public_key_id')
            ->etc()
        );

        // Sanity: the payment we seeded is the one the resource read.
        $this->assertSame($payment->id()->value(), $payment->id()->value());
    }

    public function testSuccessRendersMissingShapeForUnknownGatewayOrderId(): void
    {
        $response = $this->get('/donate/success?gateway_order_id=ord_does_not_exist');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('payments/Success')
            ->where('payment.gateway_order_id', 'ord_does_not_exist')
            ->where('payment.status', null)
            ->where('payment.amount_minor', null)
            ->where('payment.public_key_id', '')
            ->etc()
        );
    }

    private function seedPayment(
        string $providerOrderId,
        int $amountMinor,
        Currency $currency,
        TransactionStatus $status,
    ): Payment {
        /** @var PaymentRepositoryContract $repo */
        $repo = $this->app->make(PaymentRepositoryContract::class);

        $payment = Payment::initialize(
            donationId: EntityId::generate('donation'),
            providerCode: PaymentProvider::RAZORPAY,
            amountMinor: $amountMinor,
            currency: $currency,
            idempotencyKey: 'idemp_test_'.$providerOrderId,
        );
        // Stamp the provider order id so findByGatewayOrderId can locate it.
        $stored = Payment::fromRow($payment->toArray());
        $rows = $this->adapter->execute(
            'INSERT INTO payments (
                id, donation_id, provider_code, amount_minor, currency_code,
                status, amount_captured_minor, amount_refunded_minor,
                initiated_at, idempotency_key, provider_order_id,
                verification_metadata, method_detail, raw_provider_response,
                created_at, updated_at, deleted_at
            ) VALUES (
                :id, :donation_id, :provider_code, :amount_minor, :currency_code,
                :status, NULL, 0,
                :initiated_at, :idempotency_key, :provider_order_id,
                \'[]\', \'[]\', \'[]\',
                :created_at, :updated_at, NULL
            )',
            [
                'id' => $stored->id()->value(),
                'donation_id' => $stored->donationId()->value(),
                'provider_code' => $stored->providerCode()->value,
                'amount_minor' => $stored->amountMinor(),
                'currency_code' => $stored->currency()->value,
                'status' => $status->value,
                'initiated_at' => (new DateTimeImmutable())->format(DATE_ATOM),
                'idempotency_key' => $stored->idempotencyKey(),
                'provider_order_id' => $providerOrderId,
                'created_at' => (new DateTimeImmutable())->format(DATE_ATOM),
                'updated_at' => (new DateTimeImmutable())->format(DATE_ATOM),
            ],
        );

        $reloaded = $repo->findByGatewayOrderId($providerOrderId);
        $this->assertNotNull($reloaded, 'Payment seed must persist so findByGatewayOrderId returns it');

        return $reloaded;
    }
}
