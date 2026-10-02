<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Contracts\MailTransportContract;
use App\Payments\Domain\DTOs\ReceiptDocument;
use App\Mail\Client\HostingerMailClient;
use App\Shared\Contracts\ConfigurationContract;
use App\Shared\Support\Result;

/**
 * MailSubstrate — THE substrate for the Hostinger Mail API.
 *
 * The single parent that owns every Hostinger Mail boundary plus the
 * canonical email composition logic. Mirrors the ReceiptSubstrate
 * doctrine: all logic lives here; the workers (later passes) import what
 * they need from this class and only move data across boundaries.
 *
 * Boundaries owned here:
 *   - account()            GET /api/v1/me — token order + mailboxes
 *   - sendingMailbox()     resolve the configured/first sending mailbox
 *   - send()               POST /api/v1/mailboxes/{id}/send (MailTransportContract)
 *   - quota()              per-mailbox quota + usage
 *   - verifyWebhookSignature()  constant-time HMAC check of webhook posts
 *   - normalizeWebhookEvent()   Hostinger event → canonical delivery event
 *
 * Email logic owned here:
 *   - composeReceiptEmail() — the ONE canonical receipt email (subject +
 *     body) built from the typed ReceiptDocument. Every receipt email in
 *     the system is composed by this method; no surface formats its own.
 *
 * Determinism doctrine: every call returns a Result — success carries a
 * typed payload, failure carries a stable error code from the taxonomy
 * below (mail.auth_failed / mail.rate_limited / mail.rejected /
 * mail.transport_error / mail.api_error / mail.disabled). Nothing throws
 * across this boundary.
 */
final class MailSubstrate implements MailTransportContract
{
    // Stable failure taxonomy — callers branch on these, never on prose.
    public const ERR_DISABLED = 'mail.disabled';
    public const ERR_AUTH = 'mail.auth_failed';
    public const ERR_RATE_LIMITED = 'mail.rate_limited';
    public const ERR_REJECTED = 'mail.rejected';
    public const ERR_TRANSPORT = 'mail.transport_error';
    public const ERR_API = 'mail.api_error';

    /** Canonical webhook event names (Hostinger Agentic Mail). */
    public const EVENT_MESSAGE_RECEIVED = 'message.received';

    private ?array $accountCache = null;

    public function __construct(
        private readonly HostingerMailClient $client,
        private readonly ConfigurationContract $config,
    ) {
    }

    // ════════════════════════════════════════════════════════════════
    // Account boundaries
    // ════════════════════════════════════════════════════════════════

    /**
     * The token's order + reachable mailboxes.
     *
     * @return Result<array{order_resource_id: string, mailboxes: array<int, array{resource_id: string, address: string}>}>
     */
    public function account(): Result
    {
        if ($this->accountCache !== null) {
            return Result::success($this->accountCache);
        }

        try {
            $account = $this->client->me();
            $this->accountCache = $account;

            return Result::success($account);
        } catch (\Throwable $e) {
            return Result::failure($this->mapError($e));
        }
    }

    /**
     * Resolve the sending mailbox: the configured pin when present,
     * otherwise the first mailbox reachable by the token.
     *
     * @return Result<array{resource_id: string, address: string}>
     */
    public function sendingMailbox(): Result
    {
        $pinnedId = (string) $this->config->get('hostinger-mail.sending_mailbox.resource_id', '');
        $pinnedAddress = (string) $this->config->get('hostinger-mail.sending_mailbox.address', '');

        if ($pinnedId !== '' && $pinnedAddress !== '') {
            return Result::success([
                'resource_id' => $pinnedId,
                'address' => $pinnedAddress,
            ]);
        }

        $account = $this->account();
        if ($account->isFailure()) {
            return $account;
        }

        /** @var array{order_resource_id: string, mailboxes: array<int, array{resource_id: string, address: string}>} $data */
        $data = $account->value();
        foreach ($data['mailboxes'] as $mailbox) {
            if ($pinnedId === '' || $mailbox['resource_id'] === $pinnedId) {
                return Result::success($mailbox);
            }
        }

        return Result::failure(self::ERR_REJECTED.': no sending mailbox reachable for this token');
    }

    // ════════════════════════════════════════════════════════════════
    // Send boundary (MailTransportContract)
    // ════════════════════════════════════════════════════════════════

    /**
     * @param  array<int, string>               $to
     * @param  array<int, array<string, mixed>> $attachments
     * @return Result<array{accepted: bool, dry_run: bool, from: string|null}>
     */
    public function send(
        array $to,
        string $subject,
        string $text,
        string $html,
        ?string $displayName = null,
        array $attachments = [],
    ): Result {
        if (! $this->isEnabled()) {
            return Result::failure(self::ERR_DISABLED);
        }

        if ($to === []) {
            return Result::failure(self::ERR_REJECTED.': no recipients');
        }

        $mailbox = $this->sendingMailbox();
        if ($mailbox->isFailure()) {
            return $mailbox;
        }

        /** @var array{resource_id: string, address: string} $from */
        $from = $mailbox->value();

        // Deterministic dry-run: the full pipeline (compose → resolve →
        // shape) runs, nothing leaves the system.
        if ((bool) $this->config->get('hostinger-mail.dry_run', false)) {
            return Result::success([
                'accepted' => true,
                'dry_run' => true,
                'from' => $from['address'],
            ]);
        }

        try {
            $this->client->send(
                mailboxResourceId: $from['resource_id'],
                to: $to,
                subject: $subject,
                text: $text,
                html: $html,
                displayName: $displayName ?? $this->fromDisplayName(),
                attachments: $attachments,
            );

            return Result::success([
                'accepted' => true,
                'dry_run' => false,
                'from' => $from['address'],
            ]);
        } catch (\Throwable $e) {
            return Result::failure($this->mapError($e));
        }
    }

    public function isEnabled(): bool
    {
        if ((bool) $this->config->get('hostinger-mail.dry_run', false)) {
            return true; // dry-run exercises the pipeline without a token
        }

        $token = (string) $this->config->get('hostinger-mail.api_token', '');

        return $token !== '';
    }

    // ════════════════════════════════════════════════════════════════
    // Quota boundary
    // ════════════════════════════════════════════════════════════════

    /**
     * @return Result<array{total_usage: int, total_limit: int, total_percentage: int, supported: bool}>
     */
    public function quota(): Result
    {
        $mailbox = $this->sendingMailbox();
        if ($mailbox->isFailure()) {
            return $mailbox;
        }

        /** @var array{resource_id: string, address: string} $from */
        $from = $mailbox->value();

        try {
            return Result::success($this->client->quota($from['resource_id']));
        } catch (\Throwable $e) {
            return Result::failure($this->mapError($e));
        }
    }

    // ════════════════════════════════════════════════════════════════
    // Webhook boundaries
    // ════════════════════════════════════════════════════════════════

    /**
     * Verify a webhook POST against the stored secret.
     *
     * Accepts either of the two deterministic schemes:
     *   1. HMAC-SHA256 hex digest of the RAW request body under the
     *      configured signature header (default X-Webhook-Signature).
     *   2. The secret itself delivered verbatim under the same header
     *      (constant-time compared) — for providers that send the shared
     *      secret rather than a digest.
     *
     * Always constant-time (hash_equals). False when no secret is
     * configured — an unverifiable webhook must never be trusted.
     */
    public function verifyWebhookSignature(string $rawPayload, ?string $signatureHeader): bool
    {
        $secret = (string) $this->config->get('hostinger-mail.webhook.secret', '');
        if ($secret === '' || $signatureHeader === null || $signatureHeader === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawPayload, $secret);

        return hash_equals($expected, $signatureHeader)
            || hash_equals($secret, $signatureHeader);
    }

    /**
     * Normalize a Hostinger webhook payload into the canonical delivery
     * event shape. Unknown event types pass through with their raw type
     * preserved so a new Hostinger trigger degrades to "unmapped", never
     * to a wrong state.
     *
     * @param  array<string, mixed>  $payload
     * @return array{type: string, occurred_at: string|null, mailbox: string|null, message: array<string, mixed>, raw: array<string, mixed>}
     */
    public function normalizeWebhookEvent(array $payload): array
    {
        $type = (string) ($payload['event'] ?? $payload['type'] ?? 'unknown');
        $message = (array) ($payload['message'] ?? $payload['data'] ?? []);

        return [
            'type' => $type,
            'occurred_at' => isset($payload['occurred_at']) ? (string) $payload['occurred_at'] : null,
            'mailbox' => isset($payload['mailbox']) ? (string) $payload['mailbox'] : null,
            'message' => $message,
            'raw' => $payload,
        ];
    }

    // ════════════════════════════════════════════════════════════════
    // Email logic — the ONE canonical receipt email
    // ════════════════════════════════════════════════════════════════

    /**
     * Compose the canonical receipt-ready email from the typed document.
     *
     * Every receipt email in the system is composed here — subject, text
     * and HTML all derive from the SAME typed snapshot the receipt PDF
     * renders, so the email can never quote values the document doesn't
     * carry. PII doctrine: no donor PAN / postal address in the body;
     * the gated signed URL is the only door to those.
     *
     * @return array{subject: string, text: string, html: string}
     */
    public function composeReceiptEmail(ReceiptDocument $document, string $signedUrl): array
    {
        $appName = $this->trustName();

        $subject = sprintf('Your donation receipt %s — %s', $document->receiptNumber, $appName);

        $lines = [
            "Namaste {$document->donorName},",
            '',
            sprintf(
                'Thank you for your donation of %s towards "%s".',
                $document->amountDisplay,
                $document->campaignTitle,
            ),
            '',
            sprintf(
                'Your official receipt (%s) is ready. Click the link below to view and download it as a PDF:',
                $document->receiptNumber,
            ),
            '',
            $signedUrl,
            '',
            'This link is unique to your receipt — please do not share it. The 80G certificate (if applicable) is available on the receipt page once your PAN is on file.',
            '',
            "With gratitude,\n{$appName}",
        ];
        $text = implode("\n", $lines);

        // Minimal escaped HTML twin of the text body (same facts, same
        // order) — providers render html when present.
        $htmlLines = [
            '<p>Namaste '.e($document->donorName).',</p>',
            '<p>'.e(sprintf(
                'Thank you for your donation of %s towards "%s".',
                $document->amountDisplay,
                $document->campaignTitle,
            )).'</p>',
            '<p>'.e(sprintf(
                'Your official receipt (%s) is ready. Click the link below to view and download it as a PDF:',
                $document->receiptNumber,
            )).'</p>',
            '<p><a href="'.e($signedUrl).'">'.e($signedUrl).'</a></p>',
            '<p><small>This link is unique to your receipt — please do not share it. The 80G certificate (if applicable) is available on the receipt page once your PAN is on file.</small></p>',
            '<p>With gratitude,<br>'.e($appName).'</p>',
        ];

        return [
            'subject' => $subject,
            'text' => $text,
            'html' => implode("\n", $htmlLines),
        ];
    }

    // ════════════════════════════════════════════════════════════════
    // Internals
    // ════════════════════════════════════════════════════════════════

    private function fromDisplayName(): ?string
    {
        $name = (string) $this->config->get('hostinger-mail.from_display_name', '');

        return $name !== '' ? $name : $this->trustName();
    }

    private function trustName(): string
    {
        return (string) $this->config->get(
            'receipts.branding.trust_name',
            $this->config->get('app.name', 'Temple Trust'),
        );
    }

    /**
     * Stable error taxonomy. Anything thrown across the SDK boundary is
     * mapped to one of the mail.* codes so callers branch on constants,
     * never on prose or HTTP internals.
     */
    private function mapError(\Throwable $e): string
    {
        $code = (int) $e->getCode();

        return match (true) {
            $code === 401, $code === 403 => self::ERR_AUTH,
            $code === 429 => self::ERR_RATE_LIMITED,
            $code >= 400 && $code < 500 => self::ERR_REJECTED,
            $code >= 500 => self::ERR_API,
            default => self::ERR_TRANSPORT,
        };
    }
}
