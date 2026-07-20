<?php

declare(strict_types=1);

namespace App\Cms\Domain\Exceptions;

use App\Cms\Domain\Enums\CmsTransitionEvent;
use App\Cms\Domain\Enums\StaticPageState;
use App\Shared\Exceptions\DomainException;

/**
 * Thrown when a state transition violates the StaticPageStateMachine
 * rules. State transitions are decided exclusively by the state
 * machine; any direct attempt to mutate `static_pages.state` outside
 * the machine MUST surface this exception.
 */
final class InvalidPageStateTransitionException extends DomainException
{
    public function __construct(
        string $message,
        private readonly StaticPageState $fromState,
        private readonly StaticPageState $toState,
        private readonly ?string $event = null,
    ) {
        parent::__construct($message);
    }

    public static function invalidTransition(
        StaticPageState $from,
        StaticPageState $to,
        ?string $event = null,
    ): self {
        $eventSuffix = $event !== null ? sprintf(' on event [%s]', $event) : '';

        return new self(
            sprintf(
                'Invalid static page state transition: [%s] → [%s]%s',
                $from->value,
                $to->value,
                $eventSuffix,
            ),
            $from,
            $to,
            $event,
        );
    }

    public static function terminalCannotTransition(StaticPageState $terminal): self
    {
        return new self(
            sprintf(
                'Static page is in terminal state [%s]; no further transitions allowed',
                $terminal->value,
            ),
            $terminal,
            $terminal,
        );
    }

    public function errorCode(): string
    {
        return 'cms.static_page.state.transition.invalid';
    }

    public function context(): array
    {
        return [
            'from' => $this->fromState->value,
            'to' => $this->toState->value,
            'event' => $this->event,
        ];
    }
}