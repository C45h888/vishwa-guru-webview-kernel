<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Http\Middleware\IdempotencyMiddleware;
use App\Payments\Contracts\PaymentGatewayContract;
use App\Payments\Domain\DTOs\GatewayResponseDTO;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Repositories\DonationRepositoryContract;
use App\Payments\Services\PaymentProviderSelector;
use App\Persistence\ValueObjects\EntityId;
use App\Shared\Policies\LegalPolicyVersions;
use App\Shared\Support\Result;
use Inertia\Testing\AssertableInertia;
use Mockery;
use Tests\Feature\Payments\Infrastructure\InfrastructureTestCase;

final class PolicyAcceptanceCheckoutTest extends InfrastructureTestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_checkout_persists_required_policy_evidence_and_separate_optional_marketing_consent(): void
    {
        $idempotencyKey = 'policy-checkout-'.bin2hex(random_bytes(4));
        $this->useSuccessfulGateway();
        $this->withoutMiddleware([
            IdempotencyMiddleware::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
        ]);

        $response = $this->postJson('/api/v1/razorpay/checkout', $this->validPayload([
            'idempotency_key' => $idempotencyKey,
            'marketing_email_opt_in' => true,
        ]));

        $response->assertCreated();

        /** @var DonationRepositoryContract $donations */
        $donations = $this->app->make(DonationRepositoryContract::class);
        $donation = $donations->findByIdempotencyKey($idempotencyKey);
        self::assertNotNull($donation);
        self::assertSame(LegalPolicyVersions::TERMS, $donation->policyAcceptance()?->termsVersion());
        self::assertSame(LegalPolicyVersions::PRIVACY, $donation->policyAcceptance()?->privacyVersion());
        self::assertNotNull($donation->policyAcceptance()?->termsAcceptedAt());
        self::assertNotNull($donation->policyAcceptance()?->privacyAcknowledgedAt());
        self::assertSame(
            LegalPolicyVersions::MARKETING_EMAIL,
            $donation->marketingEmailConsent()?->consentVersion(),
        );
    }

    public function test_stale_privacy_version_is_rejected_before_a_donation_is_created(): void
    {
        $this->withoutMiddleware([
            IdempotencyMiddleware::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
        ]);

        $response = $this->postJson('/api/v1/razorpay/checkout', $this->validPayload([
            'policy_acceptance' => [
                'terms_version' => LegalPolicyVersions::TERMS,
                'terms_accepted' => true,
                'privacy_notice_version' => '0.9',
                'privacy_notice_acknowledged' => true,
            ],
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('policy_acceptance.privacy_notice_version');
        self::assertSame(0, (int) $this->adapter->query('SELECT COUNT(*) AS count FROM donations')->value()[0]['count']);
    }

    private function useSuccessfulGateway(): void
    {
        $gateway = Mockery::mock(PaymentGatewayContract::class);
        $gateway->shouldReceive('providerName')->andReturn('razorpay');
        $gateway->shouldReceive('enabled')->andReturn(true);
        $gateway->shouldReceive('supports')->with(Currency::INR)->andReturn(true);
        $gateway->shouldReceive('minimumAmount')->andReturn(100);
        $gateway->shouldReceive('maximumAmount')->andReturn(99_999_999);
        $gateway->shouldReceive('priority')->andReturn(1);
        $gateway->shouldReceive('initialize')->once()->andReturn(Result::success(
            new GatewayResponseDTO(
                providerCode: 'razorpay',
                gatewayOrderId: 'order_policy_'.bin2hex(random_bytes(6)),
                gatewayPaymentId: null,
                rawStatusString: 'created',
                amountMinor: 1000,
                currency: Currency::INR,
            ),
        ));

        $this->app->instance(
            PaymentProviderSelector::class,
            new PaymentProviderSelector([$gateway]),
        );
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function validPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'amount_minor' => 1000,
            'currency' => 'INR',
            'campaign_id' => EntityId::generate('campaign')->value(),
            'donor' => [
                'name' => 'A Donor',
                'email' => 'donor@example.com',
                'phone' => '+919876543210',
            ],
            'policy_acceptance' => [
                'terms_version' => LegalPolicyVersions::TERMS,
                'terms_accepted' => true,
                'privacy_notice_version' => LegalPolicyVersions::PRIVACY,
                'privacy_notice_acknowledged' => true,
            ],
            'marketing_email_opt_in' => false,
            'idempotency_key' => 'policy-checkout-'.bin2hex(random_bytes(4)),
        ], $overrides);
    }
}
