<?php

declare(strict_types=1);

namespace App\Cms\Domain\Enums;

/**
 * Resolution status for a static page reference target.
 */
enum ReferenceStatus: string
{
    case RESOLVED = 'resolved';
    case UNRESOLVED = 'unresolved';
}