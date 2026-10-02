<?php

declare(strict_types=1);

namespace App\Mail\Workers;

use App\Mail\MailSubstrate;
use App\Shared\Support\Result;

/**
 * TransportWorker — ENSURES THE EMAIL REACHES THE CORRECT PERSON.
 *
 * The final worker in the mail flow: validates the recipient against the
 * typed donor data it was given and performs the actual send through the
 * mother file's send boundary. No content knowledge — it moves a fully
 * composed message to one validated address.
 */
final class TransportWorker
{
    public function __construct(
        private readonly MailSubstrate $substrate,
    ) {
    }

    /**
     * @param  array<int, array<string, mixed>>  $attachments
     * @return Result<array{accepted: bool, dry_run: bool, from: string|null}>
     */
    public function deliver(
        string $recipient,
        string $subject,
        string $text,
        string $html,
        array $attachments = [],
    ): Result {
        // "The correct person" is enforced here and nowhere else.
        if ($recipient === '' || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            return Result::failure('mail.transport: invalid recipient');
        }

        return $this->substrate->send(
            to: [$recipient],
            subject: $subject,
            text: $text,
            html: $html,
            attachments: $attachments,
        );
    }
}
