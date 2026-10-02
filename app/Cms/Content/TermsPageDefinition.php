<?php

declare(strict_types=1);

namespace App\Cms\Content;

use App\Shared\Policies\LegalPolicyVersions;

/**
 * Canonical published content for the public Terms & Conditions page.
 *
 * The page is stored as the CMS kernel's versioned static-page blocks by
 * TermsPageSeeder. Keep the prose here plain text: PageBody's renderers
 * escape it before producing the trusted body_html projection.
 */
final class TermsPageDefinition
{
    public const ID = 'static_page_0001N6CAYNHGKPWRF7GDZ074RC';
    public const SLUG = 'terms';
    public const TITLE = 'Terms & Conditions';
    public const VERSION = LegalPolicyVersions::TERMS;
    public const DISPLAY_ORDER = 30;
    public const META_DESCRIPTION = 'Terms for using the VSRSMS website and making donations, including campaign purposes, campaign targets, payment confirmation, receipts, and refund enquiries.';

    /**
     * @return list<array{type: 'heading', level: 2, text: string}|array{type: 'paragraph', text: string}>
     */
    public static function bodyBlocks(): array
    {
        return [
            ['type' => 'paragraph', 'text' => 'Version '.self::VERSION.'. These Terms describe the use of this website and the conditions that apply when you make a donation through it. They take effect when published on this website.'],

            ['type' => 'heading', 'level' => 2, 'text' => 'The Trust and this website'],
            ['type' => 'paragraph', 'text' => '“The Trust” means Sri Vishwaguru Sri Sri Sriram Shishyavrundham Mahasamsthanam (VSRSMS). This website provides information about the Trust and a facility to make donations to campaigns that are open to receive them.'],
            ['type' => 'paragraph', 'text' => 'Please check the description and status of a campaign before donating. A campaign that is closed is not available as a donation destination.'],

            ['type' => 'heading', 'level' => 2, 'text' => 'Campaign donations and their use'],
            ['type' => 'paragraph', 'text' => 'When you select an active campaign, your donation is intended to support the cause described for that campaign. Campaigns may support different activities of the Trust. The campaign description identifies the purpose for which contributions are being invited.'],
            ['type' => 'paragraph', 'text' => 'If a campaign reaches its published target, contributions up to that target support the campaign’s stated cause. If contributions exceed the target, the excess may be used for the well-being of the Trust and its other activities.'],
            ['type' => 'paragraph', 'text' => 'If a campaign closes without reaching its target, the contributions already received may be used for the well-being and day-to-day activities of the Trust. A shortfall against a campaign target does not by itself trigger an automatic refund.'],
            ['type' => 'paragraph', 'text' => 'The general fund supports the well-being of children in the Trust’s care, including their care and education. Other campaigns may support distinct purposes, such as a cowshelter or temple construction. A donation is associated with the campaign selected at checkout, subject to the target and closure provisions above.'],

            ['type' => 'heading', 'level' => 2, 'text' => 'Campaign closure and refund enquiries'],
            ['type' => 'paragraph', 'text' => 'The donation system does not accept new donations to a campaign once it is closed. If you believe a payment was processed for a campaign after it closed, or you see a payment error or duplicate charge, contact the Trust through the Contact page. The Trust’s team will review the matter and handle any applicable refund manually after you contact the Trust.'],
            ['type' => 'paragraph', 'text' => 'A payment enquiry or refund request is not completed automatically by submitting this website form. Please include enough information for the Trust to identify the payment; do not send full card details or payment passwords.'],

            ['type' => 'heading', 'level' => 2, 'text' => 'Payments and receipts'],
            ['type' => 'paragraph', 'text' => 'Donations are currently accepted in Indian rupees through the payment method presented at checkout and within the Trust’s India-only payment scope. Payment processing is provided by the relevant gateway and is also subject to that provider’s terms and availability.'],
            ['type' => 'paragraph', 'text' => 'A donation is treated as successful only after the payment is confirmed by the payment gateway and the Trust’s system. A pending, failed, or unverified browser message is not confirmation that a donation was received.'],
            ['type' => 'paragraph', 'text' => 'The Trust issues an official receipt for a successfully confirmed donation. A receipt does not by itself guarantee that a donation qualifies for a tax deduction. Any tax treatment depends on the Trust’s applicable status, the donor’s eligibility, the receipt details, and the law in force.'],

            ['type' => 'heading', 'level' => 2, 'text' => 'Using the website'],
            ['type' => 'paragraph', 'text' => 'Use the website lawfully and do not interfere with its security, availability, or operation. Website information is provided to explain the Trust’s work and campaigns; campaign details and availability may change as the Trust’s activities develop.'],
            ['type' => 'paragraph', 'text' => 'Payment services are provided by third parties. The Trust does not control a gateway’s systems or availability. If a payment appears pending, failed, duplicated, or otherwise incorrect, contact the Trust using the Contact page so the transaction can be checked.'],

            ['type' => 'heading', 'level' => 2, 'text' => 'Questions and updates'],
            ['type' => 'paragraph', 'text' => 'For questions about these Terms, a campaign, a donation, or a payment, contact the Trust through the Contact page on this website. The Trust may update these Terms when its services or requirements change. The version published on this page is the version currently in effect.'],
        ];
    }
}
