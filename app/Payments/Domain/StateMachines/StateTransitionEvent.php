<?php

declare(strict_types=1);

namespace App\Payments\Domain\StateMachines;

/**
 * Unified event vocabulary consumed by every state machine in the
 * Financial Kernel.
 *
 * Each machine documents which subset of events it processes; events
 * outside a machine's accepted set are rejected with
 * PaymentStateTransitionException.
 */
enum StateTransitionEvent: string
{
    // Payment lifecycle
    case AUTH_OK='auth_ok';
    case CAPTURE_RECEIVED = 'capture_received';
    case SETTLEMENT_NOTICE = 'settlement_notice';
    case SETTLEMENT_CONFIRMED = 'settlement_confirmed';
    case SETTLEMENT_FAILED = 'settlement_failed';

    // Gateway negative outcomes
    case GATEWAY_CONFIRMED = 'gateway_confirmed';
    case GATEWAY_FAILED = 'gateway_failed';
    case GATEWAY_TIMEOUT = 'gateway_timeout';
    case WEBHOOK_TIMEOUT = 'webhook_timeout';

    // Customer / operator actions
    case CUSTOMER_CANCELLED = 'customer_cancelled';
    case SUBMITTED = 'submitted';

    // Refund lifecycle
    case REFUND_INITIATED = 'refund_initiated';
    case PARTIAL_REFUND_INITIATED = 'partial_refund_initiated';
    case REFUND_COMPLETED = 'refund_completed';

    // Dispute lifecycle
    case DISPUTE_OPENED = 'dispute_opened';
    case DISPUTE_RESOLVED_LOST = 'dispute_resolved_lost';

    // Donation-lifecycle events
    case RECEIPT_ISSUED = 'receipt_issued';
    case RECEIPT_FAILED = 'receipt_failed';
    case COMPLETED = 'completed';
    case POST_COMMIT_FAIL = 'post_commit_fail';

    // Receipt delivery events
    case DELIVERY_DISPATCHED = 'delivery_dispatched';
    case DELIVERY_BOUNCED = 'delivery_bounced';
    case DELIVERY_FAILED = 'delivery_failed';
    case DELIVERY_REDISPATCHED = 'delivery_redispatched';

    public function label(): string
    {
        $labels = [
            self::AUTH_OK->value => 'Auth OK',
            self::CAPTURE_RECEIVED->value => 'Capture received',
            self::SETTLEMENT_NOTICE->value => 'Settlement notice',
            self::SETTLEMENT_CONFIRMED->value => 'Settlement confirmed',
            self::SETTLEMENT_FAILED->value => 'Settlement failed',
            self::GATEWAY_CONFIRMED->value => 'Gateway confirmed',
            self::GATEWAY_FAILED->value => 'Gateway failed',
            self::GATEWAY_TIMEOUT->value => 'Gateway timeout',
            self::WEBHOOK_TIMEOUT->value => 'Webhook timeout',
            self::CUSTOMER_CANCELLED->value => 'Customer cancelled',
            self::SUBMITTED->value => 'Submitted',
            self::REFUND_INITIATED->value => 'Refund initiated',
            self::PARTIAL_REFUND_INITIATED->value => 'Partial refund initiated',
            self::REFUND_COMPLETED->value => 'Refund completed',
            self::DISPUTE_OPENED->value => 'Dispute opened',
            self::DISPUTE_RESOLVED_LOST->value => 'Dispute resolved (lost)',
            self::RECEIPT_ISSUED->value => 'Receipt issued',
            self::RECEIPT_FAILED->value => 'Receipt failed',
            self::COMPLETED->value => 'Completed',
            self::POST_COMMIT_FAIL->value => 'Post-commit fail',
            self::DELIVERY_DISPATCHED->value => 'Delivery dispatched',
            self::DELIVERY_BOUNCED->value => 'Delivery bounced',
            self::DELIVERY_FAILED->value => 'Delivery failed',
            self::DELIVERY_REDISPATCHED->value => 'Delivery re-dispatched',
        ];

        return $labels[$this->value];
    }
}
