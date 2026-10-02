<?php

declare(strict_types=1);

namespace App\Cms\Content;

use App\Shared\Policies\LegalPolicyVersions;

/**
 * Canonical published content for the public Privacy Policy page.
 *
 * Kept as plain-text CMS blocks so PageBody's renderers escape all copy
 * before it is stored as the public body_html projection.
 */
final class PrivacyPageDefinition
{
    public const ID = 'static_page_0001N6CAYNHGKPWRF7GDZ074RD';
    public const SLUG = 'privacy';
    public const TITLE = 'Privacy Policy';
    public const VERSION = LegalPolicyVersions::PRIVACY;
    public const DISPLAY_ORDER = 31;
    public const META_DESCRIPTION = 'How VSRSMS collects and uses donor information to process donations, issue receipts, meet accounting and compliance needs, and send campaign email only when a donor opts in.';

    /**
     * @return list<array{type: 'heading', level: 2, text: string}|array{type: 'paragraph', text: string}>
     */
    public static function bodyBlocks(): array
    {
        return [
            ['type' => 'paragraph', 'text' => 'Version '.self::VERSION.'. This Privacy Policy explains how Sri Vishwaguru Sri Sri Sriram Shishyavrundham Mahasamsthanam (VSRSMS, “the Trust”) handles information when you use this website, contact the Trust, or make a donation. It takes effect when published on this website.'],

            ['type' => 'heading', 'level' => 2, 'text' => 'Information we collect'],
            ['type' => 'paragraph', 'text' => 'If you make an identified donation, the donation form may collect your name, email address, and phone number. It also records the selected campaign, donation amount and currency, payment and receipt references, and any message or purpose you choose to provide.'],
            ['type' => 'paragraph', 'text' => 'Where you provide them for a receipt or applicable compliance purpose, the Trust may also process your PAN and postal address. These fields are not required for every donation. If you choose anonymous giving, the Trust’s donation record is created without the donor-identifying details that the form otherwise collects. The payment gateway may separately process information needed to complete a payment under its own privacy terms.'],
            ['type' => 'paragraph', 'text' => 'When you use the website, ordinary technical information may also be processed to operate sessions, protect the site, prevent abuse, and diagnose errors. This may include browser, device, request, and security-log information.'],

            ['type' => 'heading', 'level' => 2, 'text' => 'How we use information'],
            ['type' => 'paragraph', 'text' => 'The Trust uses donation information to create and administer the donation, send it through the payment gateway, confirm payment, issue receipts, answer donation enquiries, maintain accounts and records, meet applicable compliance obligations, and protect the payment service against errors or misuse. Donation review for these purposes is for accounting and compliance; it is not donor scoring.'],
            ['type' => 'paragraph', 'text' => 'The email address supplied for a donation may be used for necessary donation-related messages, such as payment or receipt communications. These service messages are separate from campaign marketing.'],

            ['type' => 'heading', 'level' => 2, 'text' => 'Optional campaign and promotional email'],
            ['type' => 'paragraph', 'text' => 'The Trust will use your email address for future campaign or promotional email only if you separately opt in. This choice is optional and is not required to make a donation. Leaving it unchecked does not prevent necessary payment, receipt, or support messages.'],
            ['type' => 'paragraph', 'text' => 'You can withdraw your marketing choice or unsubscribe using the instructions in a campaign email, when available, or by contacting the Trust through the Contact page. If an external email service is introduced, the Trust will update this notice to describe the service before using it for opted-in campaign email.'],

            ['type' => 'heading', 'level' => 2, 'text' => 'Who may receive information'],
            ['type' => 'paragraph', 'text' => 'Donation and payment details are shared with Razorpay or the payment provider shown at checkout as needed to initiate and verify a payment. The Trust also uses service providers to host, store, secure, and operate the website and its records. Those providers process information for the services they provide to the Trust.'],
            ['type' => 'paragraph', 'text' => 'The Trust does not send donor information to an email marketing provider for campaign outreach unless the donor has opted in. Service providers may process information in the locations where their services operate; the Trust will use providers and processing arrangements in accordance with applicable requirements and update this notice when material arrangements change. Information may also be disclosed where required to meet legal obligations, respond to a lawful request, protect the Trust or donors, or investigate suspected fraud or security issues.'],

            ['type' => 'heading', 'level' => 2, 'text' => 'Retention and security'],
            ['type' => 'paragraph', 'text' => 'The Trust retains donation, payment, receipt, and compliance records for as long as needed to administer its work and meet applicable accounting, tax, audit, and legal obligations. Marketing contact preferences are retained as needed to respect the donor’s choice, including an unsubscribe or withdrawal. Retention periods depend on the record and the obligations that apply.'],
            ['type' => 'paragraph', 'text' => 'The Trust uses access controls and technical measures intended to protect personal information. Information sent over the internet or processed by a payment or infrastructure provider may also be subject to that provider’s systems and terms. No internet service can promise absolute security.'],

            ['type' => 'heading', 'level' => 2, 'text' => 'Your choices and privacy requests'],
            ['type' => 'paragraph', 'text' => 'You may contact the Trust to ask about personal information associated with a donation, request a correction, or withdraw from optional campaign email. The Trust will review requests under applicable requirements. Some donation and financial records may need to be retained even when a donor asks that other information be removed.'],
            ['type' => 'paragraph', 'text' => 'For privacy questions or requests, contact the Trust using the Contact page on this website. Please include enough information for the Trust to locate your request, but do not send full card details, passwords, or payment authentication codes.'],

            ['type' => 'heading', 'level' => 2, 'text' => 'Cookies and website operation'],
            ['type' => 'paragraph', 'text' => 'The website uses session and security technologies required to operate pages and protect forms, including donation checkout. Some pages load font files from Google Fonts, and payment checkout uses Razorpay; those providers receive the technical connection information needed to deliver their services and are subject to their own privacy notices. Other third-party services may use their own technologies under their own notices.'],

            ['type' => 'heading', 'level' => 2, 'text' => 'Donation availability and policy updates'],
            ['type' => 'paragraph', 'text' => 'Donations through the current checkout are accepted in Indian rupees within the Trust’s India-only payment scope. The Privacy Policy may be updated when the website, donation process, service providers, or applicable requirements change. The version shown on this page is the version currently in effect.'],
        ];
    }
}
