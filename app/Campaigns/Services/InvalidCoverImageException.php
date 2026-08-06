<?php

declare(strict_types=1);

namespace App\Campaigns\Services;

use RuntimeException;

/**
 * InvalidCoverImageException — raised when a cover image upload fails
 * validation (bad MIME, oversized, unreadable, etc.).
 *
 * Doctrine: the controller catches this exception and returns a 422
 * with the message attached to the form so the admin sees what went
 * wrong without needing to inspect the server logs.
 */
final class InvalidCoverImageException extends RuntimeException
{
}
