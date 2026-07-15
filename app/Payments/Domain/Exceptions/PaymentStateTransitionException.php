<?php

declare(strict_types=1);

namespace App\Payments\Domain\Exceptions;

use App\Payments\Domain\Enums\TransactionStatus;
use App\Shared\Exceptions\DomainException;

/**
 * Thrown when a state transition violates the PaymentStateMachine rules.
 *
 * State transitions are decided exclusively by PaymentStateMachine.
 * Any direct attempt to mutate payment.status or donation.state outside
 * the state machine MUST surface this exception.
 */
final class PaymentStateTransitionException extends DomainException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        private readonly TransactionStatus $fromStatus,
        private readonly TransactionStatus $toStatus,
        private readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public static function invalidTransition(
        TransactionStatus $from,
        TransactionStatus $to,
        ?string $event = null,
    ): self {
        $eventSuffix = $event !== null ? sprintf(' on event [%s]', $event) : '';

        return new self(
            sprintf(
                'Invalid payment state transition: [%s] → [%s]%s',
                $from->value,
                $to->value,
                $eventSuffix,
            ),
            $from,
            $to,
            $event !== null ? ['event' => $event] : [],
        );
    }

    public static function terminalCannotTransition(TransactionStatus $terminal): self
    {
        return new self(
            sprintf('Payment is in terminal state [%s]; no further transitions allowed', $terminal->value),
            $terminal,
            $terminal,
        );
    }

    public static function missingPrerequisite(TransactionStatus $current, string $requiredField): self
    {
        return new self(
            sprintf(
                'Payment in state [%s] cannot transition: required field [%s] is not populated',
                $current->value,
                $requiredField,
            ),
            $current,
            $current,
            ['required_field' => $requiredField],
        );
    }

    public function errorCode(): string
    {
        return 'payments.state.transition.invalid';
    }

    public function fromStatus(): TransactionStatus
    {
        return $this->fromStatus;
    }

    public function toStatus(): TransactionStatus
    {
        return $this->toStatus;
    }

    public function context(): array
    {
        return [
            'from' => $this->fromStatus->value,
            'to' => $this->toStatus->value,
            'context' => $this->context,
        ];
    }
}