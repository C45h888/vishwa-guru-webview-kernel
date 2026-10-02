<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Http\Requests;

use App\Payments\Http\Requests\RazorpayCheckoutRequest;
use App\Persistence\ValueObjects\EntityId;
use App\Shared\Policies\LegalPolicyVersions;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class RazorpayCheckoutRequestTest extends TestCase
{
    public function test_canonical_typed_campaign_id_is_accepted(): void
    {
        $validator = $this->validate([
            'amount_minor' => 100,
            'currency' => 'INR',
            'campaign_id' => EntityId::generate('campaign')->value(),
        ]);

        $this->assertFalse($validator->fails(), implode(', ', $validator->errors()->all()));
    }

    public function test_bare_campaign_ulid_is_rejected(): void
    {
        $campaignId = EntityId::generate('campaign');
        $validator = $this->validate([
            'amount_minor' => 100,
            'currency' => 'INR',
            'campaign_id' => $campaignId->ulid(),
        ]);

        $this->assertTrue($validator->fails());
        $this->assertNotEmpty($validator->errors()->get('campaign_id'));
    }

    public function test_terms_and_privacy_acknowledgement_are_required(): void
    {
        $validator = $this->validate([
            'amount_minor' => 100,
            'currency' => 'INR',
            'campaign_id' => $this->campaignId(),
            'donor' => ['name' => null, 'email' => null, 'phone' => null],
        ], includePolicies: false);

        $this->assertTrue($validator->fails());
        $this->assertNotEmpty($validator->errors()->get('policy_acceptance.terms_accepted'));
        $this->assertNotEmpty($validator->errors()->get('policy_acceptance.privacy_notice_acknowledged'));
    }

    public function test_stale_policy_version_is_rejected(): void
    {
        $validator = $this->validate([
            'amount_minor' => 100,
            'currency' => 'INR',
            'campaign_id' => $this->campaignId(),
            'policy_acceptance' => [
                'terms_version' => '0.9',
                'terms_accepted' => true,
                'privacy_notice_version' => LegalPolicyVersions::PRIVACY,
                'privacy_notice_acknowledged' => true,
            ],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertNotEmpty($validator->errors()->get('policy_acceptance.terms_version'));
    }

    public function test_marketing_opt_in_requires_identified_email_but_is_not_required(): void
    {
        $anonymous = $this->validate([
            'amount_minor' => 100,
            'currency' => 'INR',
            'campaign_id' => $this->campaignId(),
            'marketing_email_opt_in' => true,
        ]);

        $this->assertTrue($anonymous->fails());
        $this->assertNotEmpty($anonymous->errors()->get('marketing_email_opt_in'));

        $identifiedWithoutMarketing = $this->validate([
            'amount_minor' => 100,
            'currency' => 'INR',
            'campaign_id' => $this->campaignId(),
            'donor' => [
                'name' => 'A Devotee',
                'email' => 'devotee@example.com',
                'phone' => '+919876543210',
            ],
            'marketing_email_opt_in' => false,
        ]);
        $this->assertFalse($identifiedWithoutMarketing->fails(), implode(', ', $identifiedWithoutMarketing->errors()->all()));

        $identifiedWithMarketing = $this->validate([
            'amount_minor' => 100,
            'currency' => 'INR',
            'campaign_id' => $this->campaignId(),
            'donor' => [
                'name' => 'A Devotee',
                'email' => 'devotee@example.com',
                'phone' => '+919876543210',
            ],
            'marketing_email_opt_in' => true,
        ]);
        $this->assertFalse($identifiedWithMarketing->fails(), implode(', ', $identifiedWithMarketing->errors()->all()));
    }

    public function test_anonymous_donation_without_email_or_phone_is_accepted(): void
    {
        $validator = $this->validate([
            'amount_minor' => 100,
            'currency' => 'INR',
            'campaign_id' => $this->campaignId(),
            'donor' => [
                'name' => null,
                'email' => null,
                'phone' => null,
            ],
        ]);

        $this->assertFalse($validator->fails(), implode(', ', $validator->errors()->all()));
    }

    public function test_identified_donation_requires_email_and_phone(): void
    {
        $validator = $this->validate([
            'amount_minor' => 100,
            'currency' => 'INR',
            'campaign_id' => $this->campaignId(),
            'donor' => [
                'name' => 'A Devotee',
            ],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertNotEmpty($validator->errors()->get('donor.email'));
        $this->assertNotEmpty($validator->errors()->get('donor.phone'));
    }

    public function test_identified_donation_with_email_and_phone_is_accepted(): void
    {
        $validator = $this->validate([
            'amount_minor' => 100,
            'currency' => 'INR',
            'campaign_id' => $this->campaignId(),
            'donor' => [
                'name' => 'A Devotee',
                'email' => 'devotee@example.com',
                'phone' => '+919876543210',
            ],
        ]);

        $this->assertFalse($validator->fails(), implode(', ', $validator->errors()->all()));
    }

    public function test_lowercase_pan_is_normalized_and_accepted(): void
    {
        $validator = $this->validatePrepared([
            'amount_minor' => 500_000,
            'currency' => 'INR',
            'campaign_id' => $this->campaignId(),
            'donor' => [
                'name' => 'A Devotee',
                'email' => 'devotee@example.com',
                'phone' => '+919876543210',
                'pan' => 'abcde1234f',
            ],
        ]);

        $this->assertFalse($validator->fails(), implode(', ', $validator->errors()->all()));
        $this->assertSame('ABCDE1234F', $validator->getData()['donor']['pan']);
    }

    public function test_spaced_pan_is_normalized_and_accepted(): void
    {
        $validator = $this->validatePrepared([
            'amount_minor' => 500_000,
            'currency' => 'INR',
            'campaign_id' => $this->campaignId(),
            'donor' => [
                'name' => 'A Devotee',
                'email' => 'devotee@example.com',
                'phone' => '+919876543210',
                'pan' => 'AB CDE-1234 F',
            ],
        ]);

        $this->assertFalse($validator->fails(), implode(', ', $validator->errors()->all()));
        $this->assertSame('ABCDE1234F', $validator->getData()['donor']['pan']);
    }

    public function test_empty_pan_is_normalized_to_null(): void
    {
        $validator = $this->validatePrepared([
            'amount_minor' => 500_000,
            'currency' => 'INR',
            'campaign_id' => $this->campaignId(),
            'donor' => [
                'name' => 'A Devotee',
                'email' => 'devotee@example.com',
                'phone' => '+919876543210',
                'pan' => '   ',
            ],
        ]);

        $this->assertFalse($validator->fails(), implode(', ', $validator->errors()->all()));
        $this->assertNull($validator->getData()['donor']['pan']);
    }

    public function test_invalid_pan_is_rejected_after_normalization(): void
    {
        $validator = $this->validatePrepared([
            'amount_minor' => 500_000,
            'currency' => 'INR',
            'campaign_id' => $this->campaignId(),
            'donor' => [
                'name' => 'A Devotee',
                'email' => 'devotee@example.com',
                'phone' => '+919876543210',
                'pan' => 'ABCDE12345',
            ],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertNotEmpty($validator->errors()->get('donor.pan'));
    }

    /**
     * Validate an input payload against the live FormRequest rules. The
     * request MUST carry the input (merge/replace) before rules() runs,
     * because the "required when identified" rule reads donor.name from
     * the request's own input — a bare (new RazorpayCheckoutRequest)
     * has none, so the condition would never fire.
     *
     * @param  array<string, mixed>  $data
     */
    private function validate(array $data, bool $includePolicies = true): \Illuminate\Validation\Validator
    {
        $request = new RazorpayCheckoutRequest();
        if ($includePolicies) {
            $data = $this->withPolicyAcceptance($data);
        }
        $request->replace($data);

        $validator = Validator::make($request->all(), $request->rules());
        $request->withValidator($validator);

        return $validator;
    }

    /**
     * Same as validate(), but runs prepareForValidation() first so the
     * boundary normalizations (PAN casing/separators) are exercised the
     * way they are in the real request lifecycle.
     *
     * @param  array<string, mixed>  $data
     */
    private function validatePrepared(array $data): \Illuminate\Validation\Validator
    {
        $request = new RazorpayCheckoutRequest();
        $request->replace($this->withPolicyAcceptance($data));

        $prepare = new \ReflectionMethod($request, 'prepareForValidation');
        $prepare->setAccessible(true);
        $prepare->invoke($request);

        $validator = Validator::make($request->all(), $request->rules());
        $request->withValidator($validator);

        return $validator;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function withPolicyAcceptance(array $data): array
    {
        $data['policy_acceptance'] ??= [
            'terms_version' => LegalPolicyVersions::TERMS,
            'terms_accepted' => true,
            'privacy_notice_version' => LegalPolicyVersions::PRIVACY,
            'privacy_notice_acknowledged' => true,
        ];
        $data['marketing_email_opt_in'] ??= false;

        return $data;
    }

    private function campaignId(): string
    {
        return EntityId::generate('campaign')->value();
    }
}
