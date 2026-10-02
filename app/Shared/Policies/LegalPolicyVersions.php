<?php

declare(strict_types=1);

namespace App\Shared\Policies;

/**
 * Current versions shared by the CMS policy pages and Payments checkout.
 *
 * Bump a policy version whenever its published meaning changes. The
 * Payments kernel records the version presented and accepted by the donor;
 * it does not import CMS page-definition implementations.
 */
final class LegalPolicyVersions
{
    public const TERMS = '1.0';
    public const PRIVACY = '1.0';
    public const MARKETING_EMAIL = 'campaign-email-v1';

    private function __construct()
    {
    }
}
