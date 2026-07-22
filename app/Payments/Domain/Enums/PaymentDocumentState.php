<?php

declare(strict_types=1);

namespace App\Payments\Domain\Enums;

enum PaymentDocumentState: string
{
    case PENDING = 'pending';
    case GENERATED = 'generated';
    case ISSUED = 'issued';
    case SUPERSEDED = 'superseded';
    case ARCHIVED = 'archived';
}
