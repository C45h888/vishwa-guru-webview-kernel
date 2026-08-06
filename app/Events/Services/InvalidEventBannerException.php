<?php

declare(strict_types=1);

namespace App\Events\Services;

use RuntimeException;

/**
 * InvalidEventBannerException — raised when a banner image upload fails
 * validation (bad MIME, oversized, unreadable, etc.).
 */
final class InvalidEventBannerException extends RuntimeException
{
}
