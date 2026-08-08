<?php

declare(strict_types=1);

namespace App\Payments\Http\Controllers;

use App\Payments\Http\Requests\RazorpayCheckoutRequest;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\ValueObjects\DonationIntent;
use App\Payments\Domain\ValueObjects\DonorIdentity;
use App\Payments\Services\PaymentService;
use App\Persistence\ValueObjects\EntityId;
use Illuminate\Http\JsonResponse;

/**
 * Initiate a Razorpay Standard Checkout session.
 *
 * Doctrine (constitutional):
 *   - The controller is pure transport. It builds a DonationIntent from
 *     validated input, calls PaymentService::initialize (which delegates to
 *     PaymentOrchestrator → PaymentProviderSelector → RazorpayAdapter →
 *     RazorpayClient → SDK), and shapes the response.
 *   - State transitions happen ONLY inside the orchestrator under the
 *     PaymentStateMachine. This controller never constructs Payment or
 *     Donation entities, never calls the gateway directly, and never
 *     mutates state.
 *   - The Razorpay public key is exposed in the response because it is
 *     required by the frontend checkout.js modal. The KEY SECRET never
 *     leaves the server.
 *
 * Once executed end-to-end, the response carries enough data for a Blade
 * view to open the checkout modal via the official Razorpay JS SDK
 * (https://checkout.razorpay.com/v1/checkout.js). The frontend is outside
 * the scope of this controller.
 */
final class RazorpayCheckoutController
{
    public function __construct(
        private readonly PaymentService $payments,
    ) {}

    public function store(RazorpayCheckoutRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $intent = new DonationIntent(
            campaignId: EntityId::fromString((string) $validated['campaign_id']),
            donor: $this->buildDonor($validated['donor'] ?? []),
            amountMinor: (int) $validated['amount_minor'],
            currency: Currency::from($validated['currency']),
            donorMessage: isset($validated['donation_message']) ? (string) $validated['donation_message'] : null,
            internalNotes: isset($validated['internal_notes']) ? (string) $validated['internal_notes'] : null,
            idempotencyKey: isset($validated['idempotency_key']) ? (string) $validated['idempotency_key'] : null,
        );

        $result = $this->payments->initialize($intent);

        if ($result->isFailure()) {
            return response()->json(
                ['error' => (string) $result->error()],
                422,
            );
        }

        $paymentResult = $result->value();

        // The Razorpay public key is read at the controller boundary so the
        // secret never reaches the view layer. config() is the documented
        // way to read ConfigurationContract-backed values from controllers.
        $keyId = (string) config('payments.providers.razorpay.key_id', '');

        return response()->json(
            [
                'order_id' => $paymentResult->gatewayOrderId(),
                'amount_minor' => $paymentResult->amountMinor(),
                'currency' => $paymentResult->currency()->value,
                'provider' => $paymentResult->provider()->value,
                'status' => $paymentResult->status()->value,
                'key_id' => $keyId,
            ],
            201,
        );
    }

    /**
     * @param  array<string, mixed>  $donorData
     */
    private function buildDonor(array $donorData): DonorIdentity
    {
        $name = isset($donorData['name']) ? trim((string) $donorData['name']) : '';
        if ($name === '') {
            return DonorIdentity::anonymous();
        }

        return DonorIdentity::identified(
            name: $name,
            email: $this->nullIfEmpty($donorData['email'] ?? null),
            phone: $this->nullIfEmpty($donorData['phone'] ?? null),
            pan: $this->nullIfEmpty($donorData['pan'] ?? null),
            address: $this->normalizeAddress($donorData['address'] ?? null),
        );
    }

    /**
     * @param  array<string, mixed>|null  $address
     * @return array<string, string>|null
     */
    private function normalizeAddress(?array $address): ?array
    {
        if ($address === null) {
            return null;
        }
        $clean = [];
        foreach ($address as $key => $value) {
            $value = is_string($value) ? trim($value) : '';
            if ($value !== '') {
                $clean[(string) $key] = $value;
            }
        }
        return $clean === [] ? null : $clean;
    }

    private function nullIfEmpty(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }
}
