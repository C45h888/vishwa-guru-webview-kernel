<?php

declare(strict_types=1);

namespace App\Payments\Http\Controllers;

use App\Payments\Http\Requests\RazorpayWebhookRequest;
use App\Payments\Domain\Enums\PaymentProvider;
use App\Payments\Domain\ValueObjects\WebhookPayload;
use App\Payments\Services\PaymentService;
use App\Shared\Support\Clock;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives Razorpay webhooks and hands them to the orchestrator.
 *
 * Doctrine (constitutional):
 *   - The controller is a pure transport adapter. It parses inbound HTTP
 *     into a WebhookPayload and calls PaymentService::handleWebhook,
 *     which delegates to PaymentOrchestrator → PaymentVerificationService
 *     → RazorpayVerificationAdapter (HMAC) → state transition.
 *   - Signature verification is the canonical authorization gate. It runs
 *     inside the orchestrator via RazorpayVerificationAdapter, NOT here.
 *     Returning the right HTTP status code based on the orchestrator's
 *     Result is the controller's only logic.
 *   - State transitions happen ONLY via the PaymentStateMachine inside
 *     the orchestrator. This controller never touches entities,
 *     repositories, or the gateway.
 *   - The response semantics follow Razorpay's published contract:
 *       200 — accepted (state transition committed or no-op replay)
 *       401 — signature missing or invalid
 *       422 — payload parse error, amount mismatch, missing order_id
 *       5xx — server-side error (Laravel default path)
 */
final class RazorpayWebhookController
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly Clock $clock,
    ) {}

    public function handle(RazorpayWebhookRequest $request): JsonResponse
    {
        $rawBody = $request->getContent();

        if ($rawBody === '') {
            return $this->error('empty_body', 400);
        }

        $decoded = json_decode($rawBody, true);
        if (! is_array($decoded)) {
            return $this->error('payload_not_json', 422);
        }

        $gatewayOrderId = $this->extractOrderId($decoded);
        if ($gatewayOrderId === '') {
            return $this->error('missing_gateway_order_id', 422);
        }

        $headers = $this->flattenHeaders($request);

        $eventId = (string) $request->headers->get('X-Razorpay-Event-Id', '');

        $payload = new WebhookPayload(
            provider: PaymentProvider::RAZORPAY,
            headers: $headers,
            rawBody: $rawBody,
            receivedAt: $this->clock->now(),
            providerEventId: $eventId,
            metadata: [
                'gateway_order_id' => $gatewayOrderId,
                'gateway_payment_id' => $this->extractNestedString(
                    $decoded,
                    ['payload', 'payment', 'entity', 'id']
                ),
                'client_ip' => (string) ($request->ip() ?? ''),
                'request_id' => (string) $request->headers->get('X-Request-Id', ''),
            ],
        );

        $result = $this->payments->handleWebhook($payload);

        if ($result->isFailure()) {
            $message = (string) $result->error();
            $status = $this->statusForError($message);

            return $this->error($message, $status);
        }

        return response()->json(['ok' => true], 200);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function extractOrderId(array $body): string
    {
        // Razorpay's payload shape:
        //   payload.payment.entity.order_id   (payment.* events)
        //   payload.order.entity.id            (order.* events)
        $paths = [
            ['payload', 'payment', 'entity', 'order_id'],
            ['payload', 'order', 'entity', 'id'],
        ];
        foreach ($paths as $path) {
            $value = $this->extractNestedString($body, $path);
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<int, string>  $path
     */
    private function extractNestedString(array $body, array $path): string
    {
        $cursor = $body;
        foreach ($path as $key) {
            if (! is_array($cursor) || ! array_key_exists($key, $cursor)) {
                return '';
            }
            $cursor = $cursor[$key];
        }
        return is_string($cursor) ? $cursor : '';
    }

    /**
     * Flatten Symfony's HeaderBag into the array<string,string> shape that
     * WebhookPayload requires. Multi-value headers take the first value.
     *
     * @return array<string, string>
     */
    private function flattenHeaders(Request $request): array
    {
        $flattened = [];
        foreach ($request->headers->all() as $name => $values) {
            if (! is_array($values) || $values === []) {
                continue;
            }
            $flattened[(string) $name] = (string) ($values[0] ?? '');
        }
        // Ensure at least the Host header is present (WebhookPayload rejects empty headers arrays).
        if ($flattened === [] && $request->getHost() !== '') {
            $flattened['Host'] = (string) $request->getHost();
        }
        return $flattened;
    }

    private function statusForError(string $message): int
    {
        if ($message === '' || $message === '0') {
            return 422;
        }
        $lower = strtolower($message);
        // Signature-stage errors map to 401. Everything else is 422.
        if (str_contains($lower, 'signature')
            || str_contains($lower, 'invalid_signature')
            || str_contains($lower, 'missing_signature')) {
            return 401;
        }
        return 422;
    }

    private function error(string $message, int $status): JsonResponse
    {
        return response()->json(['error' => $message], $status);
    }
}
