# Privacy Policy, Terms, and Checkout Consent

**Status:** `/terms`, `/privacy`, and the checkout consent flow are implemented. This file is the internal requirements and operations note; it is not legal advice. The Trust's authorised representative and qualified Indian legal/tax advisers should review the public wording before treating it as legally final.

**Approval owner:** The Trust's authorised contractor, with final policy review by the Trust's authorised representative and qualified advisers.

## 1. Confirmed product meaning

- A donation to an active campaign is intended for the cause stated on that campaign.
- Once a campaign target is met, amounts above the target may support the well-being of the Trust and its other activities.
- If a campaign closes below target, the amount already raised may support the well-being and day-to-day activities of the Trust. Missing a target does not itself trigger an automatic refund.
- Closed campaigns cannot accept new donations. A donor who believes a payment was processed after closure, or sees an error/duplicate charge, contacts the Trust; the team reviews it and handles any applicable refund manually.
- The general fund supports children in the Trust's care, including their care and education. Public wording uses “children in the Trust's care,” not “orphans.”
- Donation assessment means accounting and compliance, not donor scoring or marketing segmentation.
- The current payment scope is India/INR. Campaigns are not changed by this policy/consent work.

## 2. Published pages and shared versioning

- `/terms` and `/privacy` are separate published CMS pages using the generic static-page renderer. `/legal` remains the registrations/certificates page.
- `App\Shared\Policies\LegalPolicyVersions` is the single version source consumed by CMS policy definitions and Payments checkout validation. Bump the corresponding version whenever the meaning of that policy or consent text changes.
- `Database\Seeders\LegalPolicyPagesSeeder` idempotently writes only the two policy `static_pages` records; it does not reseed or modify campaigns.
- The site footer exposes separate Privacy Policy and Terms & Conditions links. Policy pages do not render the public promotional CTA or trust badge.

## 3. Checkout consent semantics

The donor first completes the donation form. After the form's existing campaign, amount, donor, phone, and PAN validation succeeds—and before any checkout POST—the site opens an accessible confirmation dialog.

The dialog contains three independent choices:

1. **Required Terms acceptance:** unchecked by default, “I agree to the Terms & Conditions” with a link to `/terms` and the current version.
2. **Required Privacy Notice acknowledgment:** unchecked by default, “I have read the Privacy Policy” with a link to `/privacy` and the current version. This records notice acknowledgment; it is not blanket consent for all personal-data processing.
3. **Optional promotional email consent:** unchecked by default and separate from the required choices. It is shown only for an identified donor with an email address. It is never required to donate.

The donor cannot proceed to the gateway until both required acknowledgments are selected. Closing/cancelling the dialog sends no checkout request. The marketing checkbox is not required and must never be inferred from an email supplied for a receipt.

`RazorpayCheckoutRequest` validates the two positive acknowledgments and requires their submitted versions to equal the current server-owned versions. Stale versions fail closed before `PaymentService` is invoked. The controller stamps acceptance times using the Shared `Clock`; it does not trust a client timestamp. A server-side request can only record the affirmative click submitted by the client—it does not prove that a human read the documents.

## 4. Consent evidence and payment persistence

- `CheckoutPolicyAcceptance` keeps Terms acceptance and Privacy Notice acknowledgment separate, each with its own version and timestamp.
- `MarketingEmailConsent` is a distinct optional value object with a separate consent-copy version and timestamp. It requires an identified donor with an email address.
- These values travel in `DonationIntent` and are persisted as separate nullable columns on `donations`. Legacy and internal donations may have null evidence; the public Razorpay checkout request cannot omit required policy acknowledgment.
- Marketing consent is evidence only; this pass does not send campaign email or connect an external email service. Future marketing delivery must select only successful donor records with affirmative consent and must honor unsubscribe/withdrawal. Transaction and receipt communications remain separate.
- The Payments migration is `2026_10_01_000003_k_payments_add_checkout_policy_evidence.php`. It is additive and nullable for legacy donations.

## 5. Privacy information represented on the public page

The current Privacy Policy describes the information visible in the donation/contact flow: donor name, email, phone, selected campaign, amount/currency, payment and receipt references, optional message/purpose, and PAN/address where supplied for a receipt or compliance purpose. It explains that anonymous gifts omit donor-identifying details from the Trust's donation record, while the payment gateway may separately process payment information under its own notice.

It describes the following purposes: donation/payment administration and verification, receipt issuance, donor support, accounting/compliance, and security. Promotional campaign email is sent only after the separate optional opt-in. The policy describes Razorpay and operational infrastructure providers, retention according to applicable obligations, privacy requests through the Contact page, and essential session/security technologies. It does not claim that a marketing email provider is already in use.

## 6. Legal/product review still required

1. Confirm whether “well-being and day-to-day activities” includes administrative costs, and confirm the treatment if a campaign cannot proceed.
2. Confirm whether a manual refund request is limited to an erroneous/late/duplicate payment or can also be requested for ordinary donations after a campaign closes below target; confirm the contact channel and any processing time.
3. Confirm authorised staff access and applicable retention periods for donation, PAN/address, technical-log, and consent evidence.
4. Verify the precise India-only eligibility/source-of-funds rule and the payment/infrastructure providers and processing locations before making more specific geographic or cross-border statements.
5. Review the beneficiary-data/photography position separately if identifiable children’s images, stories, or other personal data are stored or published. Donor consent does not cover beneficiary information.
6. Review the final Terms and Privacy wording with the Trust's authorised representative and qualified Indian legal/tax advisers.

## 7. Verification and release notes

- `tests/Unit/Payments/Http/Requests/RazorpayCheckoutRequestTest.php` covers required acknowledgments, stale versions, and the optional email choice.
- `tests/Feature/Payments/PolicyAcceptanceCheckoutTest.php` posts through the actual checkout route with a test gateway and verifies persistence or stale-version rejection before donation creation.
- `tests/Feature/Payments/Infrastructure/DonationRepositoryTest.php` round-trips the separate policy and marketing evidence.
- `tests/Feature/Ui/TermsPageTest.php` runs the isolated page seeder twice and verifies `/terms` and `/privacy` render through the CMS route.
- Before enabling a marketing email provider, add/verify withdrawal synchronization and its suppression behavior. The current opt-in record is not an email-sending integration.
