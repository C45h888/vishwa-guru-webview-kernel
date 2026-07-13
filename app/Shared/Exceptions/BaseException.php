<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

/**
 * Base exception for all application-specific errors.
 * Framework exceptions (ValidationException, NotFoundException) are used directly.
 * This hierarchy is reserved for domain-specific exceptions.
 */
class BaseException extends \Exception
{
    /**
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Report or log the exception.
     * Override in subclasses to add contextual logging.
     */
    public function report(): void
    {
        report($this);
    }
}
