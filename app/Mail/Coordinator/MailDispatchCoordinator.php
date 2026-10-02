<?php

declare(strict_types=1);

namespace App\Mail\Coordinator;

use App\Mail\Contracts\DeliveryBookkeepingContract;
use App\Mail\MailSubstrate;
use App\Mail\Workers\EmailMakingWorker;
use App\Mail\Workers\TransientTransportWorker;
use App\Mail\Workers\TransportWorker;
use App\Payments\Domain\Entities\Receipt;
use App\Payments\Domain\Enums\ReceiptDeliveryState;
use App\Shared\Support\Result;

/**
 * MailDispatchCoordinator — THE SEAM between the payments system and the
 * mail package. The only object both sides know; the only entry the
 * payments system may call; the only sequencer of the mail workers.
 *
 * Position: between. Payments hands it a persisted Receipt + the signed
 * receipt URL; it hands back a typed outcome. Everything else crosses
 * through ports:
 *
 *   Payments ──(Receipt + signedUrl)──▶ Coordinator ──▶ Workers
 *      ▲                                    │
 *      └── DeliveryBookkeepingContract ◀────┘ (delivered / failed + audit)
 *
 * Worker chain (the dense methodology, mirroring receipts):
 *   1. TransientTransportWorker — pulls the typed document + donor data
 *      (via the predefined DB workers) + the stored receipt PDF
 *   2. EmailMakingWorker        — writes the email (canonical body +
 *      PDF attachment)
 *   3. TransportWorker          — ensures the email reaches the correct
 *      person through the substrate's send boundary
 *
 * Work-boundary rules enforced here (and nowhere else):
 *   1. Transport must be enabled (token or dry-run).
 *   2. A donor email must exist — receipts without one are never mailed.
 *   3. Idempotent: an already-delivered receipt is a no-op success.
 *   4. Every send outcome is recorded through the bookkeeping port — the
 *      runtime state and the real world cannot drift.
 *
 * Non-final so unit tests can substitute a recording stub via
 * inheritance (queue-carrier tests assert delegation); production code
 * resolves through DI and never sees a subclass.
 */
class MailDispatchCoordinator
{
    public function __construct(
        private readonly MailSubstrate $substrate,
        private readonly TransientTransportWorker $dataWorker,
        private readonly EmailMakingWorker $emailWorker,
        private readonly TransportWorker $transportWorker,
        private readonly DeliveryBookkeepingContract $bookkeeping,
    ) {
    }

    /**
     * Deliver the canonical receipt email for a persisted receipt.
     *
     * @return Result<array{status: string, from: string|null, recipient: string}>
     *     status: 'sent' | 'dry_run' | 'already_delivered'
     */
    public function deliverReceipt(Receipt $receipt, string $signedUrl): Result
    {
        // Boundary 1 — transport configured.
        if (! $this->substrate->isEnabled()) {
            return Result::failure('mail.dispatch: '.MailSubstrate::ERR_DISABLED);
        }

        // Boundary 2 — a recipient must exist (nothing to book if not).
        $recipient = $receipt->donorEmail();
        if ($recipient === null || $recipient === '') {
            return Result::failure('mail.dispatch: mail.no_recipient');
        }

        // Boundary 3 — idempotency: never double-send a delivered receipt.
        if ($receipt->deliveryStatus() === ReceiptDeliveryState::DELIVERED->value
            || $receipt->isDelivered()) {
            return Result::success([
                'status' => 'already_delivered',
                'from' => null,
                'recipient' => $recipient,
            ]);
        }

        // 1 — pull typed data + the hash-true PDF artifact.
        $bundle = $this->dataWorker->fetch($receipt);
        if ($bundle->isFailure()) {
            return Result::failure('mail.dispatch: '.(string) $bundle->error());
        }

        /** @var array{document: \App\Payments\Domain\DTOs\ReceiptDocument, pdf_bytes: string|null} $payload */
        $payload = $bundle->value();

        // 2 — write the email from the typed document.
        $message = $this->emailWorker->compose(
            $payload['document'],
            $signedUrl,
            $payload['pdf_bytes'],
        );
        if ($message->isFailure()) {
            return Result::failure('mail.dispatch: '.(string) $message->error());
        }

        /** @var array{subject: string, text: string, html: string, attachments: array<int, array<string, mixed>>} $composed */
        $composed = $message->value();

        // The correct person = the donor email on the typed document.
        $to = $payload['document']->donorEmail ?? $recipient;

        // 3 — send to the correct person through the substrate boundary.
        $send = $this->transportWorker->deliver(
            recipient: $to,
            subject: $composed['subject'],
            text: $composed['text'],
            html: $composed['html'],
            attachments: $composed['attachments'],
        );

        // Boundary 4 — every outcome is booked.
        if ($send->isFailure()) {
            $error = (string) $send->error();
            $this->bookkeeping->markFailed($receipt, $to, $error);

            return Result::failure('mail.dispatch: '.$error);
        }

        /** @var array{accepted: bool, dry_run: bool, from: string|null} $sent */
        $sent = $send->value();
        $this->bookkeeping->markDelivered($receipt, $to);

        return Result::success([
            'status' => $sent['dry_run'] ? 'dry_run' : 'sent',
            'from' => $sent['from'],
            'recipient' => $to,
        ]);
    }
}
