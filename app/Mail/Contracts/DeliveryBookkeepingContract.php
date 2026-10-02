<?php

declare(strict_types=1);

namespace App\Mail\Contracts;

/**
 * DeliveryBookkeepingContract — the mail package's outbound bookkeeping
 * port ("record the delivery outcome for receipt X").
 *
 * Implemented on the Payments side (App\Payments\Mail\DeliveryBookkeepingAdapter)
 * which drives ReceiptService::markDelivered() through the
 * ReceiptStateMachine (delivered / failed) and writes the audit-trail
 * entry. The mail package never touches the delivery FSM directly.
 */
interface DeliveryBookkeepingContract
{
    public function markDelivered(\App\Payments\Domain\Entities\Receipt $receipt, string $recipient): void;

    public function markFailed(\App\Payments\Domain\Entities\Receipt $receipt, string $recipient, string $reason): void;
}
