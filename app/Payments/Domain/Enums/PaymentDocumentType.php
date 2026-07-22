<?php

declare(strict_types=1);

namespace App\Payments\Domain\Enums;

enum PaymentDocumentType: string
{
    case DONATION_PROOF = 'donation_proof';
    case RECEIPT = 'receipt';
    case CERTIFICATE_80G = 'certificate_80g';
    case REFUND_EVIDENCE = 'refund_evidence';
    case GATEWAY_EVIDENCE = 'gateway_evidence';
}
